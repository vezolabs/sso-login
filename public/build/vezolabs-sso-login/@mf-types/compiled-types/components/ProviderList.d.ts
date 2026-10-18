import React from 'react';
import { IdentityProvider } from '../types';
interface ProviderListProps {
    providers: IdentityProvider[];
    loading: boolean;
    onEdit: (provider: IdentityProvider) => void;
    onToggle: (id: number) => void;
    onDelete: (id: number) => void;
    onTest: (slug: string) => void;
}
export declare const ProviderList: React.FC<ProviderListProps>;
export {};
