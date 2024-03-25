import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersExtensionsEditRoute extends Route {
    @service store;

    model(params) {
        return this.store.queryRecord('registry-extension', { public_id: params.public_id, single: true });
    }

    setupController(controller) {
        super.setupController(...arguments);
        const isReady = controller.validateExtensionForReview();
        if (isReady === true) {
            controller.isReady = isReady;
        }
    }
}
