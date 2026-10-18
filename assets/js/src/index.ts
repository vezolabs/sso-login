import { SsoManagementPage } from './pages/SsoManagementPage';
import { SsoLoginButton } from './components/SsoLoginButton';

function resolveService(container: any, ids: string[]) {
  if (!container) return null;
  for (const id of ids) {
    try {
      if (typeof container.isBound === 'function' && container.isBound(id)) {
        return container.get(id);
      }
    } catch (_) {}
    try {
      const svc = container.get(id);
      if (svc) return svc;
    } catch (_) {}
  }
  return null;
}

let registered = false;

function doRegister(container: any) {
  if (registered || !container) return;
  registered = true;

  try {
    // 1. Register Widget in Widget Manager
    const widgetRegistry = resolveService(container, [
      'WidgetManagerService',
      'widgetManager',
      'App/WidgetManager/WidgetRegistry',
      'widgetRegistry'
    ]);

    if (widgetRegistry && typeof widgetRegistry.registerWidget === 'function') {
      widgetRegistry.registerWidget({
        name: 'sso-login',
        component: SsoManagementPage,
      });
    }

    // 2. Register Navigation Items (under System menu and Settings)
    const mainNavRegistry = resolveService(container, [
      'MainNavRegistry',
      'mainNavRegistry',
      'App/MainNavRegistry/MainNavRegistry'
    ]);

    if (mainNavRegistry && typeof mainNavRegistry.registerMainNavItem === 'function') {
      // Primary entry under System (the shield menu in Studio sidebar)
      mainNavRegistry.registerMainNavItem({
        path: 'System/SSO Login',
        label: 'SSO Login',
        order: 850,
        icon: 'shield',
        widgetConfig: {
          name: 'sso-login',
          id: 'sso-login',
          component: 'sso-login',
          config: {
            title: 'SSO Login',
            icon: {
              type: 'name',
              value: 'shield',
            },
          },
        },
      });

      // Secondary entry under Settings if present
      mainNavRegistry.registerMainNavItem({
        path: 'Settings/SSO Login',
        label: 'SSO Login',
        order: 850,
        icon: 'shield',
        widgetConfig: {
          name: 'sso-login',
          id: 'sso-login',
          component: 'sso-login',
          config: {
            title: 'SSO Login',
            icon: {
              type: 'name',
              value: 'shield',
            },
          },
        },
      });
    }

    // 3. Register Login Screen Slot for Single Sign-On Button
    const componentRegistry = resolveService(container, [
      'App/ComponentRegistry/ComponentRegistry',
      'componentRegistry'
    ]);

    if (componentRegistry && typeof componentRegistry.registerToSlot === 'function') {
      componentRegistry.registerToSlot('form.login', {
        name: 'vezolabsSsoLogin',
        priority: 10,
        component: SsoLoginButton,
      });
    }
  } catch (e) {
    console.error('[VezoLabs SSO] Plugin registration error:', e);
  }
}

export const VezolabsSsoPlugin = {
  name: 'VezolabsSsoLoginBundle',
  priority: 10,

  onInit: ({ container }: { container: any }) => {
    doRegister(container);
  },

  onStartup: () => {
    const globalContainer = (window as any).Pimcore?.container;
    if (globalContainer) {
      doRegister(globalContainer);
    }
  },
};

export default VezolabsSsoPlugin;
