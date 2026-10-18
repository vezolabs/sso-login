# Microsoft Entra ID (Azure AD) Setup Guide

This guide walks you through setting up SAML 2.0 Single Sign-On between **Microsoft Entra ID** and **Pimcore 12 Studio** using the built-in Studio Setup Wizard.

---

## Prerequisites

1. An active Microsoft Entra ID tenant with permissions to manage Enterprise Applications.
2. Pimcore 12 running with `vezolabs/pimcore-sso-login-bundle` installed.
3. Access to Pimcore Studio with administrative credentials.

---

## Step 1: Open the Setup Wizard

1. In Pimcore Studio, navigate to **Settings → SSO Login**.
2. Click on the **Entra ID Wizard** tab (or click **Add Identity Provider → Microsoft Entra ID**).

---

## Step 2: Create Enterprise Application in Microsoft Entra ID

1. Open the [Microsoft Entra admin center](https://entra.microsoft.com/).
2. Navigate to **Identity → Applications → Enterprise applications**.
3. Select **New application → Create your own application**.
4. Enter an application name, e.g., `Pimcore Studio`.
5. Select **Integrate any other application you don't find in the gallery (Non-gallery)**.
6. Click **Create**.

---

## Step 3: Configure Basic SAML Configuration in Azure

1. In your new enterprise application, select **Manage → Single sign-on**.
2. Select **SAML**.
3. Under **1. Basic SAML Configuration**, click **Edit**:
   - **Identifier (Entity ID)**: Copy the value from **Step 3 (Pimcore SP Details)** in the Studio wizard (e.g., `https://your-domain.com/sso/metadata/entra-id`).
   - **Reply URL (Assertion Consumer Service URL)**: Copy the ACS URL from the Studio wizard (e.g., `https://your-domain.com/sso/acs/entra-id`).
   - **Sign on URL** (optional): `https://your-domain.com/pimcore-studio/`.
   - **Relay State** (optional): Leave empty or set to `/pimcore-studio/`.
   - **Logout URL** (optional): `https://your-domain.com/sso/logout/entra-id`.
4. Click **Save**.

---

## Step 4: Configure Attributes & Claims in Azure

Under **2. Attributes & Claims**, click **Edit**:
Ensure the following standard claims are mapped (Entra ID default claims are natively supported):

| Claim Name | Value / Source Attribute |
|---|---|
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` | `user.mail` (or `user.userprincipalname`) |
| `http://schemas.microsoft.com/identity/claims/objectidentifier` | `user.objectid` |
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname` | `user.givenname` |
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname` | `user.surname` |
| `http://schemas.microsoft.com/identity/claims/displayname` | `user.displayname` |
| `http://schemas.microsoft.com/ws/2008/06/identity/claims/role` | `user.assignedroles` (or security groups) |

---

## Step 5: Import Azure Metadata into Pimcore

In Azure under **3. SAML Certificates**:
- **Method A (Federation Metadata XML)**: Download the **Federation Metadata XML** from Azure and upload it in the Studio Wizard. The bundle will automatically extract the IdP Entity ID, SSO Login URL, and X.509 Signing Certificate.
- **Method B (Federation Metadata URL)**: Copy the **App Federation Metadata Url** from Azure, paste it into the URL field in Studio, and click **Fetch & Validate**.

---

## Step 6: Validate & Test Configuration

1. In the Studio wizard, click **Validate Configuration**. The system will verify:
   - IdP Entity ID presence
   - HTTPS SSO URL validity
   - X.509 certificate validity and expiration date
   - SP ACS URL compatibility
2. Click **Test SSO Login**. A test SAML request will be launched in preview mode to verify identity claims and role mappings without creating live user records.

---

## Step 7: Map Roles and Activate

1. In the **Role Mapping** tab, map your Azure application roles or group names to your Pimcore roles (e.g., `Pimcore.Admins` → `admin`).
2. Set your **Unmapped Role Policy** (`Deny access` recommended).
3. Toggle the provider to **Enabled**.
4. The **Sign in with Microsoft** button is now live on your Pimcore Studio login screen!
