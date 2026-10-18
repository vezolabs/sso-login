import { IdentityProvider, OverviewMetrics, ValidationResult, AuditLogItem, CertificateItem, PimcoreRole, PublicConfigResponse } from '../types';
export declare const ssoApi: {
    getOverview: () => Promise<OverviewMetrics>;
    getProviders: () => Promise<{
        items: IdentityProvider[];
    }>;
    getProvider: (id: number) => Promise<{
        provider: IdentityProvider;
        validation: ValidationResult;
    }>;
    createProvider: (provider: Partial<IdentityProvider>) => Promise<{
        message: string;
        provider: IdentityProvider;
    }>;
    updateProvider: (id: number, provider: Partial<IdentityProvider>) => Promise<{
        message: string;
        provider: IdentityProvider;
    }>;
    deleteProvider: (id: number) => Promise<{
        message: string;
    }>;
    toggleProvider: (id: number) => Promise<{
        message: string;
        enabled: boolean;
    }>;
    rotateCertificate: (id: number) => Promise<{
        message: string;
        provider: IdentityProvider;
    }>;
    parseXml: (xml: string) => Promise<{
        parsed: any;
        certificate: any;
        rollover_certificate?: any;
    }>;
    fetchMetadataUrl: (url: string) => Promise<{
        raw_xml: string;
        parsed: any;
        certificate: any;
        rollover_certificate?: any;
    }>;
    getPimcoreRoles: () => Promise<{
        roles: PimcoreRole[];
    }>;
    getLogs: (params?: {
        status?: string;
        provider_name?: string;
        username?: string;
        page?: number;
        limit?: number;
    }) => Promise<{
        items: AuditLogItem[];
        total: number;
        page: number;
        limit: number;
    }>;
    getCertificates: () => Promise<{
        certificates: CertificateItem[];
    }>;
    getPublicConfig: () => Promise<PublicConfigResponse>;
};
