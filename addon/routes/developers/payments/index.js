import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersPaymentsIndexRoute extends Route {
    @service fetch;

    async setupController(controller) {
        try {
            const { hasStripeConnectAccount } = await this.fetch.get('payments/has-stripe-connect-account', {}, { namespace: '~registry/v1' });
            controller.hasStripeConnectAccount = hasStripeConnectAccount;
        } catch (error) {
            controller.hasStripeConnectAccount = false;
        }
    }
}
