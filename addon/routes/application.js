import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ApplicationRoute extends Route {
    @service fetch;

    async setupController(controller) {
        super.setupController(...arguments);
        // controller.categories = await this.store.query('category', { for: 'extension_category', core_category: 1 });
        controller.categories = await this.fetch.get('categories', {}, { namespace: '~registry/v1', normalizeToEmberData: true, modelType: 'category' });
    }
}
