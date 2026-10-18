import React from 'react';
import { IdentityProvider } from '../types';
interface EntraIdWizardProps {
    initialProvider?: IdentityProvider | null;
    onFinished: () => void;
    onCancel: () => void;
}
export declare const EntraIdWizard: React.FC<EntraIdWizardProps>;
export {};
