import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';

export default class ExploreCategoryRoute extends Route {
    @service store;
    @tracked categorySlug;

    queryParams = {
        query: {
            refreshModel: true,
        },
    };

    model({ slug, query }) {
        this.categorySlug = slug;
        return this.store.query('registry-extension', { explore: 1, category: slug, query });
    }

    async setupController(controller) {
        super.setupController(...arguments);
        controller.category = await this.store.queryRecord('category', { slug: this.categorySlug, for: 'extension_category', core_category: 1, single: 1 });
    }
}
