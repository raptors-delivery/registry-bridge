import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class DevelopersExtensionsEditController extends Controller {
    @service store;
    @service fetch;
    @service notifications;
    @tracked isReady = false;
    @tracked subscriptionModelOptions = ['flat_rate', 'tiered', 'usage'];
    @tracked billingPeriodOptions = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
    @tracked uploadQueue = [];
    acceptedFileTypes = ['image/jpeg', 'image/png', 'image/gif'];

    @task *save() {
        try {
            yield this.model.save();
            this.validateExtensionForReview();
        } catch (error) {
            this.notifications.warning(error.message);
        }
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

    @task *uploadBuild(file) {
        yield this.fetch.uploadFile.perform(file, {
            path: `uploads/extensions/${this.model.id}/builds`,
            subject_uuid: this.model.id,
            subject_type: 'registry-bridge:registry-extension',
            type: 'extension_build',
            meta: {
                version: this.model.version,
            },
        });
    }

    @action queueFile(file) {
        // since we have dropzone and upload button within dropzone validate the file state first
        // as this method can be called twice from both functions
        if (['queued', 'failed', 'timed_out', 'aborted'].indexOf(file.state) === -1) {
            return;
        }

        // Queue and upload immediatley
        this.uploadQueue.pushObject(file);
        this.fetch.uploadFile.perform(
            file,
            {
                path: `uploads/extensions/${this.model.id}/screenshots`,
                subject_uuid: this.model.id,
                subject_type: 'registry-bridge:registry-extension',
                type: 'extension_screenshot',
            },
            (uploadedFile) => {
                this.model.screenshots.pushObject(uploadedFile);
                this.uploadQueue.removeObject(file);
            },
            () => {
                this.uploadQueue.removeObject(file);
                // remove file from queue
                if (file.queue && typeof file.queue.remove === 'function') {
                    file.queue.remove(file);
                }
            }
        );
    }

    @action removeFile(file) {
        return file.destroyRecord();
    }

    validateExtensionForReview() {
        // run checks to see if extension is ready fo review
    }
}
