<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiApplication;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Services\ApiManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiManagementController extends Controller
{
    public function __construct(
        protected ApiManagementService $service
    ) {}

    /**
     * Display the Admin API Management dashboard.
     */
    public function index(Request $request): View
    {
        $app = ApiApplication::leadx();
        $metrics = $this->service->getOverviewMetrics();
        $health = $this->service->performHealthCheck();
        $groupedEndpoints = $this->service->getGroupedEndpoints();

        // Paginated API keys history
        $keys = $app->keys()->latest('id')->paginate(10, ['*'], 'keys_page');

        // Filterable request logs
        $logs = ApiRequestLog::query()
            ->filter($request->only(['from_date', 'to_date', 'endpoint', 'method', 'status']))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        // Audit logs related to API key & config operations
        $auditLogs = AuditLog::query()
            ->where(function ($q) use ($app) {
                $q->where('auditable_type', ApiKey::class)
                  ->orWhere('auditable_type', ApiApplication::class)
                  ->orWhere('event', 'like', 'api_key.%')
                  ->orWhere('event', 'like', 'api_config.%');
            })
            ->latest('id')
            ->take(15)
            ->get();

        $activeTab = $request->query('tab', 'overview');

        return view('admin.api-management.index', compact(
            'app',
            'metrics',
            'health',
            'groupedEndpoints',
            'keys',
            'logs',
            'auditLogs',
            'activeTab'
        ));
    }

    /**
     * Generate a brand-new API key for the application.
     */
    public function generateKey(Request $request): RedirectResponse
    {
        $app = ApiApplication::leadx();
        $admin = auth('admin')->user();

        $result = ApiKey::createKey($app, $admin, 'LeadX Android App Key');

        AuditLog::create([
            'auditable_type' => ApiKey::class,
            'auditable_id' => $result['key']->id,
            'event' => 'api_key.generated',
            'old_values' => null,
            'new_values' => [
                'name' => $result['key']->name,
                'prefix' => $result['key']->key_prefix,
                'status' => $result['key']->status,
                'expires_at' => $result['key']->expires_at?->toIso8601String(),
                'generated_by' => $admin?->email ?? 'super_admin',
            ],
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.api-management.index', ['tab' => 'keys'])
            ->with('status', 'API Key generated successfully! Store it securely; it will not be shown again.')
            ->with('new_api_key', $result['plain_key']);
    }

    /**
     * Regenerate an existing API key.
     */
    public function regenerateKey(Request $request, ApiKey $key): RedirectResponse
    {
        $app = ApiApplication::leadx();
        if ($key->api_application_id !== $app->id) {
            abort(404);
        }

        $admin = auth('admin')->user();
        $previousPrefix = $key->key_prefix;
        $result = $key->regenerate($admin);

        AuditLog::create([
            'auditable_type' => ApiKey::class,
            'auditable_id' => $result['key']->id,
            'event' => 'api_key.regenerated',
            'old_values' => [
                'revoked_key_id' => $key->id,
                'revoked_prefix' => $previousPrefix,
            ],
            'new_values' => [
                'name' => $result['key']->name,
                'prefix' => $result['key']->key_prefix,
                'status' => $result['key']->status,
                'expires_at' => $result['key']->expires_at?->toIso8601String(),
                'regenerated_by' => $admin?->email ?? 'super_admin',
            ],
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.api-management.index', ['tab' => 'keys'])
            ->with('status', 'API Key regenerated successfully! Previous key has been revoked.')
            ->with('new_api_key', $result['plain_key']);
    }

    /**
     * Revoke an API key.
     */
    public function revokeKey(Request $request, ApiKey $key): RedirectResponse
    {
        $app = ApiApplication::leadx();
        if ($key->api_application_id !== $app->id) {
            abort(404);
        }

        $admin = auth('admin')->user();
        $previousStatus = $key->status;
        $key->revoke();

        AuditLog::create([
            'auditable_type' => ApiKey::class,
            'auditable_id' => $key->id,
            'event' => 'api_key.revoked',
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => ApiKey::STATUS_REVOKED, 'revoked_at' => now()->toIso8601String(), 'revoked_by' => $admin?->email],
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.api-management.index', ['tab' => 'keys'])
            ->with('status', 'API Key has been revoked. Any requests using this key will be rejected.');
    }

    /**
     * Toggle active/disabled status of an API key.
     */
    public function toggleKey(Request $request, ApiKey $key): RedirectResponse
    {
        $app = ApiApplication::leadx();
        if ($key->api_application_id !== $app->id) {
            abort(404);
        }

        $oldStatus = $key->status;
        $key->toggle();
        $newStatus = $key->fresh()->status;

        AuditLog::create([
            'auditable_type' => ApiKey::class,
            'auditable_id' => $key->id,
            'event' => 'api_key.toggled',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $newStatus],
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.api-management.index', ['tab' => 'keys'])
            ->with('status', "API Key status updated to: {$newStatus}");
    }

    /**
     * Update API configuration.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'required|url|max:255',
            'version' => 'required|string|max:20',
            'rate_limit_per_minute' => 'required|integer|min:1|max:10000',
            'key_expiry_days' => 'required|integer|min:0|max:3650',
            'ip_allowlist' => 'nullable|string',
        ]);

        $app = ApiApplication::leadx();
        $oldValues = $app->only([
            'name', 'base_url', 'version', 'is_enabled',
            'rate_limit_per_minute', 'key_expiry_days',
            'ip_allowlist', 'maintenance_mode'
        ]);

        $app->update([
            'name' => $validated['name'],
            'base_url' => $validated['base_url'],
            'version' => $validated['version'],
            'rate_limit_per_minute' => (int) $validated['rate_limit_per_minute'],
            'key_expiry_days' => (int) $validated['key_expiry_days'],
            'ip_allowlist' => $validated['ip_allowlist'] ? trim($validated['ip_allowlist']) : null,
            'is_enabled' => $request->boolean('is_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
        ]);

        AuditLog::create([
            'auditable_type' => ApiApplication::class,
            'auditable_id' => $app->id,
            'event' => 'api_config.updated',
            'old_values' => $oldValues,
            'new_values' => $app->fresh()->only([
                'name', 'base_url', 'version', 'is_enabled',
                'rate_limit_per_minute', 'key_expiry_days',
                'ip_allowlist', 'maintenance_mode'
            ]),
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.api-management.index', ['tab' => 'config'])
            ->with('status', 'API Configuration settings have been successfully updated.');
    }

    /**
     * Health check JSON endpoint.
     */
    public function healthCheck(): JsonResponse
    {
        $result = $this->service->performHealthCheck();

        return response()->json($result, $result['code']);
    }

    /**
     * Download or view OpenAPI spec.
     */
    public function docsOpenApi(Request $request): BinaryFileResponse|Response
    {
        $filePath = base_path('docs/LEADX_EMPLOYEE_API_OPENAPI.yaml');
        if (! file_exists($filePath)) {
            abort(404, 'OpenAPI specification file not found.');
        }

        if ($request->query('download')) {
            return response()->download($filePath, 'leadx-api-v1-openapi.yaml', [
                'Content-Type' => 'text/yaml; charset=utf-8',
            ]);
        }

        return response(file_get_contents($filePath), 200, [
            'Content-Type' => 'text/yaml; charset=utf-8',
        ]);
    }

    /**
     * Interactive Swagger UI view.
     */
    public function docsSwagger(): View
    {
        return view('admin.api-management.swagger');
    }

    /**
     * Download Postman collection JSON.
     */
    public function docsPostman(): JsonResponse
    {
        $collection = $this->service->generatePostmanCollection();

        return response()->json($collection, 200, [
            'Content-Disposition' => 'attachment; filename="LeadX_API_Collection.postman_collection.json"',
        ]);
    }

    /**
     * View or download Flutter integration guide.
     */
    public function docsFlutter(Request $request): BinaryFileResponse|View
    {
        $filePath = base_path('docs/LEADX_FLUTTER_INTEGRATION_GUIDE.md');
        if (! file_exists($filePath)) {
            abort(404, 'Flutter integration guide file not found.');
        }

        if ($request->query('download')) {
            return response()->download($filePath, 'LeadX_Flutter_Integration_Guide.md', [
                'Content-Type' => 'text/markdown; charset=utf-8',
            ]);
        }

        $content = file_get_contents($filePath);
        return view('admin.api-management.flutter-guide', compact('content'));
    }
}
