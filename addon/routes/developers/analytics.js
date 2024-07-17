import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersAnalyticsRoute extends Route {
    @service store;

    model() {
        return this.store.query('registry-extension', { is_author: 1 });
    }
}
