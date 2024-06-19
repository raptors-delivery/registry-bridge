import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class InstalledRoute extends Route {
    @service fetch;

    model(params = {}) {
        return this.fetch.get('registry-extensions/installed', params, { namespace: '~registry/v1', normalizeToEmberData: true, modelType: 'registry-extension' });
    }
}
