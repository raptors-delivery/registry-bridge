import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersPaymentsIndexRoute extends Route {
    @service fetch;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
    };

    model() {
        return this.fetch.get('payments/author-received', {}, { namespace: '~registry/v1' });
    }

    setupController(controller) {
        controller.lookupStripeConnectAccount.perform();
    }
}
