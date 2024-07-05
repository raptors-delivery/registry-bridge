import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import services from '@fleetbase/ember-core/exports/services';
import ExtensionReviewerControlComponent from './components/extension-reviewer-control';
import ExtensionPendingPublishViewerComponent from './components/extension-pending-publish-viewer';

const { modulePrefix } = config;
const externalRoutes = ['console', 'extensions'];

export default class RegistryBridgeEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services,
        externalRoutes,
    };
    setupExtension = function (app, engine, universe) {
        // Register menu item in header
        universe.registerHeaderMenuItem('Extensions', 'console.extensions', { icon: 'shapes', priority: 99 });
        // Register admin controls
        universe.registerAdminMenuPanel(
            'Extensions Registry',
            [
                {
                    title: 'Extensions Awaiting Review',
                    icon: 'gavel',
                    component: ExtensionReviewerControlComponent,
                },
                {
                    title: 'Extensions Pending Publish',
                    icon: 'rocket',
                    component: ExtensionPendingPublishViewerComponent,
                },
            ],
            {
                slug: 'extension-registry',
            }
        );
    };
}

loadInitializers(RegistryBridgeEngine, modulePrefix);
