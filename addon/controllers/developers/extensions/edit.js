import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class DevelopersExtensionsEditController extends Controller {
    @service store;
    @service fetch;
    @service currentUser;
    @tracked uploadedFile;

    @task *save() {
        yield this.model.save();
    }

    @task *uploadIcon(file) {
        yield this.fetch.uploadFile.perform(
            file,
            {
                path: `uploads/extensions/${this.model.id}/icons`,
                subject_uuid: this.model.id,
                subject_type: 'registry-bridge:registry-extension',
                type: 'extension_icon',
            },
            (uploadedFile) => {
                this.model.setProperties({
                    icon_uuid: uploadedFile.id,
                    icon_url: uploadedFile.url,
                });

                return this.model.save();
            }
        );
    }

    @action addTag(tag) {
        this.model.tags.pushObject(tag);
    }

    @action removeTag(index) {
        this.model.tags.removeAt(index);
    }
}
