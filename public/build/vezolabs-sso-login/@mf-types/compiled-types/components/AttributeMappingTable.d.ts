import React from 'react';
import { IdentityProvider } from '../types';
interface AttributeMappingTableProps {
    provider: IdentityProvider;
    onSave: (updatedProvider: IdentityProvider) => Promise<void>;
    loading: boolean;
}
export declare const AttributeMappingTable: React.FC<AttributeMappingTableProps>;
export {};
