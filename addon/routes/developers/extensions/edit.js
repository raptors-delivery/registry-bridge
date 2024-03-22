import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersExtensionsEditRoute extends Route {
    @service store;

    model(params) {
        return this.store.findRecord('registry-extension', params.public_id);
    }

    setupController(controller) {
        super.setupController(...arguments);
        const isReady = controller.validateExtensionForReview();
        if (isReady === true) {
            controller.isReady = isReady;
        }
    }
}
