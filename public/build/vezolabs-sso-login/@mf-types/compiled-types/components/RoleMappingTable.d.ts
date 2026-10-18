import React from 'react';
import { IdentityProvider } from '../types';
interface RoleMappingTableProps {
    provider: IdentityProvider;
    onSave: (updatedProvider: IdentityProvider) => Promise<void>;
    loading: boolean;
}
export declare const RoleMappingTable: React.FC<RoleMappingTableProps>;
export {};
