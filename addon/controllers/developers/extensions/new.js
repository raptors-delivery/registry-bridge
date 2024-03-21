import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

export default class DevelopersExtensionsNewController extends Controller {
    @service store;
    @service universe;
    @service hostRouter;
    @service notifications;
    @tracked extension = this.store.createRecord('registry-extension');

    @task *save() {
        try {
            yield this.extension.save();
        } catch (error) {
            return this.notifications.warning(error.message);
        }
        return this.hostRouter.transitionTo('console.registry-bridge.developers.extensions.edit', this.extension);
    }
}
