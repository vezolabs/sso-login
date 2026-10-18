export interface IdentityProvider {
  id?: number;
  name: string;
  type: string;
  display_name: string;
  description?: string | null;
  enabled: boolean;
  is_default: boolean;
  tenant_id?: string | null;

  idp_entity_id: string;
  idp_sso_url: string;
  idp_slo_url?: string | null;
  idp_x509_cert: string;
  idp_x509_cert_rollover?: string | null;
  idp_metadata_url?: string | null;
  idp_metadata_xml?: string | null;

  sp_entity_id?: string | null;
  sp_acs_url?: string | null;
  sp_slo_url?: string | null;
  sp_x509_cert?: string | null;
  sp_private_key?: string | null;

  name_id_format: string;
  sign_authn_requests: boolean;
  want_assertions_signed: boolean;
  want_response_signed: boolean;

  login_button_label: string;
  login_button_visible: boolean;
  login_button_icon: string;
  hide_local_login: boolean;
  auto_redirect_sso: boolean;

  attribute_mapping: Record<string, string>;
  role_mapping: Record<string, string>;
  role_sync_policy: 'every_login' | 'first_login_only';
  unmapped_role_policy: 'deny' | 'assign_restricted' | 'manual_approval';
  restricted_fallback_role?: string | null;
  revoke_removed_roles: boolean;

  provisioning_enabled: boolean;
  require_mapped_roles_for_creation: boolean;
  default_pimcore_role?: string | null;
  sync_user_attributes_on_login: boolean;
  handle_disabled_users: 'deny_access' | 'do_nothing';
  allowed_domains: string[];

  created_at?: string;
  updated_at?: string;
}

export interface ValidationCheck {
  name: string;
  passed: boolean;
  severity: 'error' | 'warning' | 'info';
  message: string;
}

export interface ValidationResult {
  valid: boolean;
  checks: ValidationCheck[];
  errors: string[];
  warnings: string[];
}

export interface OverviewMetrics {
  sso_enabled: boolean;
  health_status: 'HEALTHY' | 'WARNING' | 'ERROR';
  providers_total: number;
  providers_enabled: number;
  active_provider?: { id: number; name: string; display_name: string } | null;
  sso_users_count: number;
  last_successful_login?: string | null;
  last_failed_login?: string | null;
  outstanding_warnings: string[];
}

export interface AuditLogItem {
  id: number;
  timestamp: string;
  provider_id?: number | null;
  provider_name?: string | null;
  username?: string | null;
  external_user_id?: string | null;
  status: 'SUCCESS' | 'FAILED' | 'DENIED';
  reason?: string | null;
  assigned_roles: string[];
  attributes_summary: Record<string, any>;
  ip_address?: string | null;
  user_agent?: string | null;
  correlation_id: string;
}

export interface CertificateItem {
  provider_id: number;
  provider_name: string;
  type: 'PRIMARY' | 'ROLLOVER';
  status: 'VALID' | 'EXPIRING_SOON' | 'EXPIRED' | 'INVALID';
  subject: string;
  issuer: string;
  valid_from?: string | null;
  valid_to?: string | null;
  days_left: number;
  fingerprint_sha1: string;
  fingerprint_sha256: string;
  raw_pem: string;
}

export interface PimcoreRole {
  id: number;
  name: string;
  description: string;
}

export interface PublicProviderConfig {
  slug: string;
  display_name: string;
  label: string;
  icon: string;
  login_url: string;
  is_default: boolean;
}

export interface PublicConfigResponse {
  sso_available: boolean;
  providers: PublicProviderConfig[];
  hide_local_login: boolean;
  auto_redirect_url?: string | null;
}
