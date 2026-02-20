<?php

/**
 * LogicPanel Blesta Provisioning Module — v2.0
 *
 * Integrates LogicPanel PaaS panel with Blesta billing.
 * Features: Auto package retrieval, one-click SSO login,
 * full provisioning lifecycle, client area with usage stats.
 *
 * @package  logicpanel
 * @version  2.0.0
 */
class Logicpanel extends Module
{
    /**
     * Module version (used by Blesta for upgrade checks)
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    public function getName(): string
    {
        return 'LogicPanel';
    }

    public function getDescription(): string
    {
        return 'Provision hosting accounts on LogicPanel PaaS with one-click SSO.';
    }

    public function getAuthors(): array
    {
        return [['name' => 'cyber-wahid', 'url' => 'https://cyberwahid.site/']];
    }

    /**
     * The key used to identify a server row (shown in Blesta admin)
     */
    public function moduleRowMetaKey(): string
    {
        return 'hostname';
    }

    public function moduleRowName(): string
    {
        return 'Server';
    }

    public function moduleRowNamePlural(): string
    {
        return 'Servers';
    }

    public function moduleGroupName(): string
    {
        return 'Server Group';
    }

    // ─── Constructor ───────────────────────────────────────────────────────────

    public function __construct()
    {
        // Load module language
        Language::loadLang(['logicpanel'], null, dirname(__FILE__) . DS . 'language' . DS);

        // Load the API client
        Loader::loadComponents($this, ['Input']);
        require_once dirname(__FILE__) . DS . 'lib' . DS . 'api_client.php';

        parent::__construct();
    }

    // ─── Server Row (Add/Edit server) ──────────────────────────────────────────

    /**
     * Fields shown when adding/editing a LogicPanel server in Blesta admin.
     */
    public function moduleRowFields($vars = null): array
    {
        // Load packages from API for the package dropdown
        $packageOptions = [];
        if ($vars && !empty($vars['host']) && !empty($vars['api_key'])) {
            try {
                $api = $this->getApiClient($vars['host'], (int)($vars['port'] ?? 9999), $vars['api_key'], ($vars['use_ssl'] ?? true) == 'true', $vars['host']);
                $result = $api->get('/packages');
                if ($result['success'] && !empty($result['data']['packages'])) {
                    foreach ($result['data']['packages'] as $pkg) {
                        $packageOptions[$pkg['id'] . '|' . $pkg['name']] = $pkg['name'] . ($pkg['description'] ? ' — ' . $pkg['description'] : '');
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        return [
            'host' => [
                'label' => Language::_('LogicPanel.module_row.host', true),
                'type'  => 'text',
                'value' => isset($vars['host']) ? $vars['host'] : '',
            ],
            'port' => [
                'label' => Language::_('LogicPanel.module_row.port', true),
                'type'  => 'text',
                'value' => isset($vars['port']) ? $vars['port'] : '9999',
            ],
            'user_port' => [
                'label' => Language::_('LogicPanel.module_row.user_port', true),
                'type'  => 'text',
                'value' => isset($vars['user_port']) ? $vars['user_port'] : '7777',
            ],
            'api_key' => [
                'label' => Language::_('LogicPanel.module_row.api_key', true),
                'type'  => 'text',
                'value' => isset($vars['api_key']) ? $vars['api_key'] : '',
            ],
            'use_ssl' => [
                'label'   => Language::_('LogicPanel.module_row.use_ssl', true),
                'type'    => 'checkbox',
                'value'   => 'true',
                'checked' => isset($vars['use_ssl']) ? ($vars['use_ssl'] === 'true') : true,
            ],
        ];
    }

    /**
     * Validate and add a server row
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['host', 'port', 'user_port', 'api_key', 'use_ssl'];
        $encrypted   = ['api_key'];

        $vars['use_ssl'] = isset($vars['use_ssl']) && $vars['use_ssl'] == 'true' ? 'true' : 'false';

        // Test connection
        if (!empty($vars['host']) && !empty($vars['api_key'])) {
            $api = $this->getApiClient($vars['host'], (int)($vars['port'] ?? 9999), $vars['api_key'], $vars['use_ssl'] === 'true', $vars['host']);
            $result = $api->get('/packages');
            if (!$result['success']) {
                $this->Input->setErrors(['api' => ['connection' => Language::_('LogicPanel.!error.connection', true)]]);
                return;
            }
        } else {
            $this->Input->setErrors(['api' => ['connection' => Language::_('LogicPanel.!error.api_key_required', true)]]);
            return;
        }

        return $this->arrayToModuleFields($vars, null, $meta_fields, $encrypted);
    }

    public function editModuleRow($module_row, array &$vars)
    {
        return $this->addModuleRow($vars);
    }

    // ─── Package Fields ────────────────────────────────────────────────────────

    /**
     * Fields shown when creating/editing a Blesta package that uses LogicPanel.
     */
    public function getPackageFields($vars = null, $module_row = null): ModuleFields
    {
        $fields = new ModuleFields();

        $packages = [];
        if ($module_row) {
            try {
                $api    = $this->getApiClientFromRow($module_row);
                $result = $api->get('/packages');
                if ($result['success'] && !empty($result['data']['packages'])) {
                    foreach ($result['data']['packages'] as $pkg) {
                        $label = $pkg['name'];
                        if (!empty($pkg['description'])) {
                            $label .= ' — ' . $pkg['description'];
                        }
                        $packages[$pkg['id'] . '|' . $pkg['name']] = $label;
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        // Package selector
        $package = $fields->label(Language::_('LogicPanel.package_fields.package', true), 'logicpanel_package');
        $package->attach($fields->fieldSelect('meta[package]',
            $packages,
            isset($vars->meta['package']) ? $vars->meta['package'] : null,
            ['id' => 'logicpanel_package']
        ));
        $fields->setField($package);

        return $fields;
    }

    // ─── Service Fields (optional params per service) ──────────────────────────

    public function getAdminAddFields($package, $vars = null): ModuleFields
    {
        $fields = new ModuleFields();

        // Domain field
        $field = $fields->label(Language::_('LogicPanel.service_fields.domain', true), 'logicpanel_domain');
        $field->attach($fields->fieldText('logicpanel_domain',
            isset($vars->logicpanel_domain) ? $vars->logicpanel_domain : '',
            ['id' => 'logicpanel_domain']
        ));
        $fields->setField($field);

        return $fields;
    }

    public function getClientAddFields($package, $vars = null): ModuleFields
    {
        return $this->getAdminAddFields($package, $vars);
    }

    // ─── Provisioning ──────────────────────────────────────────────────────────

    public function addService($package, array $vars = null, $parent_package = null, $parent_service = null, $status = 'pending')
    {
        // Only provision on active status
        if ($status != 'active') {
            return null;
        }

        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);

        $pkg = $this->parsePackage($package->meta->package ?? '');

        $postData = [
            'username'   => $vars['logicpanel_username'] ?? $this->generateUsername($vars),
            'email'      => $vars['client']['email'],
            'password'   => $vars['logicpanel_password'] ?? $this->generatePassword(),
            'domain'     => $vars['logicpanel_domain'] ?? '',
            'package_id' => $pkg['id'] ?: null,
            'role'       => 'user',
        ];

        if (!empty($vars['client']['first_name'])) {
            $postData['first_name'] = $vars['client']['first_name'];
            $postData['last_name']  = $vars['client']['last_name'] ?? '';
        }

        $result = $api->post('/accounts', $postData);

        if (!$result['success']) {
            $this->Input->setErrors(['api' => ['error' => Language::_('LogicPanel.!error.api', true) . ': ' . ($result['error'] ?: 'Unknown')]]);
            return;
        }

        $accountId = $result['data']['account']['id'] ?? $result['data']['id'] ?? $result['data']['user']['id'] ?? null;

        // Return service fields saved to DB
        return [
            [
                'key'       => 'logicpanel_account_id',
                'value'     => $accountId,
                'encrypted' => 0,
            ],
            [
                'key'       => 'logicpanel_username',
                'value'     => $postData['username'],
                'encrypted' => 0,
            ],
            [
                'key'       => 'logicpanel_password',
                'value'     => $postData['password'],
                'encrypted' => 1,
            ],
            [
                'key'       => 'logicpanel_domain',
                'value'     => $postData['domain'],
                'encrypted' => 0,
            ],
            [
                'key'       => 'logicpanel_package',
                'value'     => $pkg['name'],
                'encrypted' => 0,
            ],
        ];
    }

    public function editService($package, $service, array $vars = null, $parent_package = null, $parent_service = null)
    {
        // Not typically used — return existing fields unchanged
        return null;
    }

    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        return $this->terminateAccount($package, $service);
    }

    // ─── Lifecycle Actions ─────────────────────────────────────────────────────

    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $row  = $this->getModuleRow($package->module_row);
        $api  = $this->getApiClientFromRow($row);
        $svc  = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            $this->Input->setErrors(['api' => ['error' => Language::_('LogicPanel.!error.no_account_id', true)]]);
            return;
        }

        $result = $api->post("/accounts/{$svc['logicpanel_account_id']}/suspend");
        if (!$result['success']) {
            $this->Input->setErrors(['api' => ['error' => $result['error'] ?: 'Suspend failed']]);
        }
        return null;
    }

    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            $this->Input->setErrors(['api' => ['error' => Language::_('LogicPanel.!error.no_account_id', true)]]);
            return;
        }

        $result = $api->post("/accounts/{$svc['logicpanel_account_id']}/unsuspend");
        if (!$result['success']) {
            $this->Input->setErrors(['api' => ['error' => $result['error'] ?: 'Unsuspend failed']]);
        }
        return null;
    }

    public function terminateAccount($package, $service)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            $this->Input->setErrors(['api' => ['error' => Language::_('LogicPanel.!error.no_account_id', true)]]);
            return;
        }

        $result = $api->delete("/accounts/{$svc['logicpanel_account_id']}");
        if (!$result['success']) {
            $this->Input->setErrors(['api' => ['error' => $result['error'] ?: 'Termination failed']]);
        }
        return null;
    }

    public function changeServicePackage($package_from, $package_to, $service, $parent_package = null, $parent_service = null)
    {
        $row = $this->getModuleRow($package_to->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            return;
        }

        $pkg = $this->parsePackage($package_to->meta->package ?? '');
        $api->post("/accounts/{$svc['logicpanel_account_id']}/package", ['package_id' => $pkg['id']]);

        return null;
    }

    public function changeServicePassword($package, $service, array $vars)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            $this->Input->setErrors(['api' => ['error' => Language::_('LogicPanel.!error.no_account_id', true)]]);
            return;
        }

        $result = $api->post("/accounts/{$svc['logicpanel_account_id']}/password", [
            'password' => $vars['password'],
        ]);

        if (!$result['success']) {
            $this->Input->setErrors(['api' => ['error' => $result['error'] ?: 'Password change failed']]);
            return;
        }

        // Return updated password field
        return [
            [
                'key'       => 'logicpanel_password',
                'value'     => $vars['password'],
                'encrypted' => 1,
            ],
        ];
    }

    // ─── SSO ───────────────────────────────────────────────────────────────────

    /**
     * Called when client clicks "Login" link from Blesta client area.
     * Returns a URL for one-click auto-login.
     */
    public function getServiceLoginUrl($package, $service, $module_row = null)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        if (empty($svc['logicpanel_account_id'])) {
            return $this->getPanelUrl($row);
        }

        $result = $api->post("/accounts/{$svc['logicpanel_account_id']}/login");

        if ($result['success'] && !empty($result['data']['token'])) {
            $panelUrl = $this->getPanelUrl($row);
            return rtrim($panelUrl, '/') . '/?token=' . $result['data']['token'];
        }

        return $this->getPanelUrl($row);
    }

    // ─── Client Area ───────────────────────────────────────────────────────────

    /**
     * Render the client area tab for a service.
     */
    public function getClientServiceInfo($package, $service, $vars = null, $module_row = null)
    {
        $row = $this->getModuleRow($package->module_row);
        $api = $this->getApiClientFromRow($row);
        $svc = $this->getServiceFields($service);

        $accountData   = [];
        $servicesData  = [];
        $databasesData = [];
        $domainsData   = [];
        $error         = '';
        $panelUrl      = $this->getPanelUrl($row);

        $accountId = $svc['logicpanel_account_id'] ?? null;

        if ($accountId) {
            $account = $api->get("/accounts/{$accountId}");
            if ($account['success']) {
                $accountData = $account['data']['account'] ?? $account['data'] ?? [];
            }
            $services  = $api->get("/accounts/{$accountId}/services");
            if ($services['success']) {
                $servicesData = $services['data']['services'] ?? [];
            }
            $databases = $api->get("/accounts/{$accountId}/databases");
            if ($databases['success']) {
                $databasesData = $databases['data']['databases'] ?? [];
            }
            $domains = $api->get("/accounts/{$accountId}/domains");
            if ($domains['success']) {
                $domainsData = $domains['data']['domains'] ?? [];
            }
        } else {
            $error = Language::_('LogicPanel.client_area.not_provisioned', true);
        }

        // SSO URL for the login button
        $ssoUrl = $this->getServiceLoginUrl($package, $service);

        // Template data
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'logicpanel' . DS);

        $this->view->set('account_id',    $accountId);
        $this->view->set('account_data',  $accountData);
        $this->view->set('services_data', $servicesData);
        $this->view->set('databases_data', $databasesData);
        $this->view->set('domains_data',  $domainsData);
        $this->view->set('panel_url',     $panelUrl);
        $this->view->set('sso_url',       $ssoUrl);
        $this->view->set('username',      $svc['logicpanel_username'] ?? '');
        $this->view->set('password',      $svc['logicpanel_password'] ?? '');
        $this->view->set('domain',        $svc['logicpanel_domain']   ?? '');
        $this->view->set('package',       $svc['logicpanel_package']  ?? '');
        $this->view->set('server_host',   $row->meta->host ?? '');
        $this->view->set('error',         $error);

        return $this->view->fetch();
    }

    // ─── Admin Area ────────────────────────────────────────────────────────────

    public function getAdminServiceInfo($package, $service, $vars = null, $module_row = null)
    {
        $row = $this->getModuleRow($package->module_row);
        $svc = $this->getServiceFields($service);

        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'logicpanel' . DS);

        $panelUrl  = $this->getPanelUrl($row);
        $accountId = $svc['logicpanel_account_id'] ?? null;

        $this->view->set('account_id', $accountId);
        $this->view->set('username',   $svc['logicpanel_username'] ?? '');
        $this->view->set('domain',     $svc['logicpanel_domain']   ?? '');
        $this->view->set('package',    $svc['logicpanel_package']  ?? '');
        $this->view->set('panel_url',  $panelUrl . ($accountId ? "/accounts/{$accountId}" : ''));

        return $this->view->fetch();
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function getApiClient(string $host, int $port, string $apiKey, bool $ssl, string $hostHeader): LogicPanelApiClient
    {
        return new LogicPanelApiClient($host, $port, $apiKey, $ssl, $hostHeader);
    }

    private function getApiClientFromRow($row): LogicPanelApiClient
    {
        $meta = (array)($row->meta ?? []);
        $host    = $meta['host']    ?? '';
        $port    = (int)($meta['port'] ?? 9999);
        $apiKey  = $meta['api_key'] ?? '';
        $ssl     = ($meta['use_ssl'] ?? 'true') === 'true';
        return new LogicPanelApiClient($host, $port, $apiKey, $ssl, $host);
    }

    private function getPanelUrl($row): string
    {
        $meta     = (array)($row->meta ?? []);
        $host     = $meta['host']      ?? '';
        $userPort = $meta['user_port'] ?? '7777';
        $ssl      = ($meta['use_ssl'] ?? 'true') === 'true';
        $protocol = $ssl ? 'https' : 'http';
        return "{$protocol}://{$host}:{$userPort}";
    }

    private function getServiceFields($service): array
    {
        $fields = [];
        if (is_array($service)) {
            foreach ($service as $field) {
                $fields[$field->key] = $field->value;
            }
        }
        return $fields;
    }

    private function parsePackage(string $value): array
    {
        $clean = explode('=', $value, 2)[0];
        $parts = explode('|', $clean, 2);
        return [
            'id'   => (int)($parts[0] ?? 0),
            'name' => trim($parts[1] ?? ''),
        ];
    }

    private function generateUsername(array $vars): string
    {
        if (!empty($vars['client']['username'])) {
            return preg_replace('/[^a-z0-9_]/', '', strtolower($vars['client']['username']));
        }
        return 'lp_' . strtolower(substr(md5(microtime(true)), 0, 8));
    }

    private function generatePassword(int $length = 12): string
    {
        $chars  = 'abcdefghijklmnopqrstuvwxyz';
        $upper  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $nums   = '0123456789';
        $spec   = '!@#$%^&*';
        $all    = $chars . $upper . $nums . $spec;
        $pass   = $upper[random_int(0, strlen($upper)-1)]
                . $spec[random_int(0, strlen($spec)-1)];
        for ($i = 2; $i < $length; $i++) {
            $pass .= $all[random_int(0, strlen($all)-1)];
        }
        return str_shuffle($pass);
    }

    private function arrayToModuleFields(array $vars, $module_row, array $meta_fields, array $encrypted = []): array
    {
        $fields = [];
        foreach ($meta_fields as $key) {
            if (isset($vars[$key])) {
                $fields[] = [
                    'key'       => $key,
                    'value'     => $vars[$key],
                    'encrypted' => in_array($key, $encrypted) ? 1 : 0,
                ];
            }
        }
        return $fields;
    }
}
