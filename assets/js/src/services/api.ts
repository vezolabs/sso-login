import {
  IdentityProvider,
  OverviewMetrics,
  ValidationResult,
  AuditLogItem,
  CertificateItem,
  PimcoreRole,
  PublicConfigResponse
} from '../types';

const BASE_URL = '/pimcore-studio/api/sso';

async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(options.headers || {}),
  };

  const response = await fetch(`${BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data.error || `HTTP ${response.status}: Request failed`);
  }

  return data as T;
}

export const ssoApi = {
  getOverview: () => request<OverviewMetrics>('/overview'),
  
  getProviders: () => request<{ items: IdentityProvider[] }>('/providers'),
  
  getProvider: (id: number) => request<{ provider: IdentityProvider; validation: ValidationResult }>(`/providers/${id}`),
  
  createProvider: (provider: Partial<IdentityProvider>) =>
    request<{ message: string; provider: IdentityProvider }>('/providers', {
      method: 'POST',
      body: JSON.stringify(provider),
    }),
    
  updateProvider: (id: number, provider: Partial<IdentityProvider>) =>
    request<{ message: string; provider: IdentityProvider }>(`/providers/${id}`, {
      method: 'PUT',
      body: JSON.stringify(provider),
    }),
    
  deleteProvider: (id: number) =>
    request<{ message: string }>(`/providers/${id}`, {
      method: 'DELETE',
    }),
    
  toggleProvider: (id: number) =>
    request<{ message: string; enabled: boolean }>(`/providers/${id}/toggle`, {
      method: 'POST',
    }),
    
  rotateCertificate: (id: number) =>
    request<{ message: string; provider: IdentityProvider }>(`/providers/${id}/rotate-cert`, {
      method: 'POST',
    }),
    
  parseXml: (xml: string) =>
    request<{ parsed: any; certificate: any; rollover_certificate?: any }>('/metadata/parse-xml', {
      method: 'POST',
      body: JSON.stringify({ xml }),
    }),
    
  fetchMetadataUrl: (url: string) =>
    request<{ raw_xml: string; parsed: any; certificate: any; rollover_certificate?: any }>('/metadata/fetch-url', {
      method: 'POST',
      body: JSON.stringify({ url }),
    }),
    
  getPimcoreRoles: () => request<{ roles: PimcoreRole[] }>('/roles'),
  
  getLogs: (params: { status?: string; provider_name?: string; username?: string; page?: number; limit?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.status) query.set('status', params.status);
    if (params.provider_name) query.set('provider_name', params.provider_name);
    if (params.username) query.set('username', params.username);
    if (params.page) query.set('page', String(params.page));
    if (params.limit) query.set('limit', String(params.limit));
    return request<{ items: AuditLogItem[]; total: number; page: number; limit: number }>(`/logs?${query.toString()}`);
  },
  
  getCertificates: () => request<{ certificates: CertificateItem[] }>('/certificates'),
  
  getPublicConfig: () => request<PublicConfigResponse>('/public-config'),
};
