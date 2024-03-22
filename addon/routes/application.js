import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ApplicationRoute extends Route {
    @service store;

    async setupController(controller) {
        super.setupController(...arguments);
        controller.categories = await this.store.query('category', { for: 'extension_category', core_category: 1 });
    }
}
