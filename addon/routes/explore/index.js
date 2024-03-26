import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ExploreIndexRoute extends Route {
    @service store;

    queryParams = {
        query: {
            refreshModel: true,
        },
    };

    model(params) {
        const { query } = params;
        return this.store.query('registry-extension', { explore: 1, query });
    }
}
