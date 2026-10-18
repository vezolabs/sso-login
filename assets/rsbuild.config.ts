import { defineConfig } from '@rsbuild/core';
import { pluginReact } from '@rsbuild/plugin-react';
import { pluginModuleFederation } from '@module-federation/rsbuild-plugin';
import path from 'path';
import fs from 'fs';

const buildId = 'vezolabs-sso-login';
const buildPath = path.resolve(__dirname, '..', 'public', 'build', buildId);

if (!fs.existsSync(buildPath)) {
  fs.mkdirSync(buildPath, { recursive: true });
}

// Generate exposeRemote.js and entrypoints.json plugin
const generatePimcoreStudioManifestPlugin = () => ({
  name: 'pimcore-studio-manifest-plugin',
  setup(api: any) {
    api.onAfterBuild(() => {
      const remoteEntryUrl = `/bundles/vezolabsssologin/build/${buildId}/static/js/remoteEntry.js`;
      const exposeRemoteJs = `
if (window.pluginRemotes === undefined) {
  window.pluginRemotes = {};
}
if (window.alternativePluginExportPaths === undefined) {
  window.alternativePluginExportPaths = {};
}
window.pluginRemotes.vezolabs_sso_login_bundle = "${remoteEntryUrl}";
`;
      fs.writeFileSync(path.resolve(buildPath, 'exposeRemote.js'), exposeRemoteJs.trim());

      const entrypoints = {
        entrypoints: {
          exposeRemote: {
            js: [`/bundles/vezolabsssologin/build/${buildId}/exposeRemote.js`],
            css: [],
          },
        },
      };
      fs.writeFileSync(path.resolve(buildPath, 'entrypoints.json'), JSON.stringify(entrypoints, null, 2));
    });
  },
});

export default defineConfig({
  source: {
    entry: {
      main: './js/src/main.ts',
    },
  },
  output: {
    manifest: true,
    assetPrefix: `/bundles/vezolabsssologin/build/${buildId}/`,
    distPath: {
      root: buildPath,
    },
  },
  plugins: [
    pluginReact(),
    pluginModuleFederation({
      name: 'vezolabs_sso_login_bundle',
      filename: 'static/js/remoteEntry.js',
      exposes: {
        './plugins': './js/src/plugins.ts',
      },
      shared: {
        react: {
          singleton: true,
          eager: false,
        },
        'react-dom': {
          singleton: true,
          eager: false,
        },
        antd: {
          singleton: true,
          eager: false,
        },
      },
    }),
    generatePimcoreStudioManifestPlugin(),
  ],
});
