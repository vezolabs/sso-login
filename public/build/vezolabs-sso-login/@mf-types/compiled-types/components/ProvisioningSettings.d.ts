import React from 'react';
import { IdentityProvider } from '../types';
interface ProvisioningSettingsProps {
    provider: IdentityProvider;
    onSave: (updatedProvider: IdentityProvider) => Promise<void>;
    loading: boolean;
}
export declare const ProvisioningSettings: React.FC<ProvisioningSettingsProps>;
export {};
