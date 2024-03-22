import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ExploreCategoryRoute extends Route {
    @service store;

    model({ slug }) {
        return this.store.queryRecord('category', { for: 'extension_category', core_category: 1, slug, single: 1 });
    }
}
