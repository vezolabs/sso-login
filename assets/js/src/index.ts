import { SsoManagementPage } from './pages/SsoManagementPage';
import { SsoLoginButton } from './components/SsoLoginButton';

export const VezolabsSsoPlugin = {
  name: 'VezolabsSsoLoginBundle',
  priority: 10,

  onInit: ({ container }: { container: any }) => {
    try {
      // 1. Register Main Settings Widget
      const widgetRegistry = container.get('widgetRegistry') || container.get('App/WidgetManager/WidgetRegistry');
      if (widgetRegistry?.registerWidget) {
        widgetRegistry.registerWidget({
          name: 'sso-login',
          component: SsoManagementPage,
        });
      }

      // 2. Register Navigation Entry: Settings -> SSO Login
      const mainNavRegistry = container.get('mainNavRegistry') || container.get('App/MainNavRegistry/MainNavRegistry');
      if (mainNavRegistry?.registerMainNavItem) {
        mainNavRegistry.registerMainNavItem({
          path: 'Settings/SSO Login',
          label: 'SSO Login',
          order: 1050,
          permission: 'VEZOLABS_SSO_MANAGE',
          widgetConfig: {
            name: 'sso-login',
            id: 'sso-login',
            component: 'sso-login',
            config: {
              translationKey: 'SSO Login',
              icon: {
                type: 'name',
                value: 'settings',
              },
            },
          },
        });
      }

      // 3. Register Login Screen Slot for Single Sign-On Button
      const componentRegistry = container.get('componentRegistry') || container.get('App/ComponentRegistry/ComponentRegistry');
      if (componentRegistry?.registerToSlot) {
        componentRegistry.registerToSlot('form.login', {
          name: 'vezolabsSsoLogin',
          priority: 10,
          component: SsoLoginButton,
        });
      }
    } catch (e) {
      console.error('[VezoLabs SSO] Plugin registration error:', e);
    }
  },

  onStartup: () => {
    console.log('[VezoLabs SSO] Single Sign-On plugin initialized.');
  },
};
