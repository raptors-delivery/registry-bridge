import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class DevelopersCredentialsRoute extends Route {
    @service fetch;

    model() {
        return this.fetch.get('auth/registry-tokens', {}, { namespace: '~registry/v1' });
    }
}
