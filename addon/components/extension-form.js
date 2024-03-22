import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class ExtensionFormComponent extends Component {
    @service store;
    @service fetch;
    @service notifications;
    @service intl;
    @tracked subscriptionModelOptions = ['flat_rate', 'tiered', 'usage'];
    @tracked billingPeriodOptions = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
    @tracked uploadQueue = [];
    acceptedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    acceptedBundleTypes = [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/x-compressed',
        'multipart/x-zip',
        'application/x-tar',
        'application/gzip',
        'application/x-gzip',
        'application/x-tgz',
        'application/x-bzip2',
        'application/x-xz',
    ];

    @task *uploadIcon(file) {
        const { extension, onIconUploaded } = this.args;

        yield this.fetch.uploadFile.perform(
            file,
            {
                path: `uploads/extensions/${extension.id}/icons`,
                subject_uuid: extension.id,
                subject_type: 'registry-bridge:registry-extension',
                type: 'extension_icon',
            },
            (uploadedFile) => {
                extension.setProperties({
                    icon: uploadedFile,
                    icon_uuid: uploadedFile.id,
                    icon_url: uploadedFile.url,
                });

                if (typeof onIconUploaded === 'function') {
                    onIconUploaded(uploadedFile);
                }

                return extension.save();
            }
        );
    }

    @task *uploadBundle(file) {
        const { extension, onBundleUploaded } = this.args;

        yield this.fetch.uploadFile.perform(
            file,
            {
                path: `uploads/extensions/${this.args.extension.id}/bundles`,
                subject_uuid: this.args.extension.id,
                subject_type: 'registry-bridge:registry-extension',
                type: 'extension_bundle',
                meta: {
                    version: this.args.extension.version,
                },
            },
            (uploadedFile) => {
                extension.setProperties({
                    latest_bundle: uploadedFile,
                    latest_bundle_uuid: uploadedFile.id,
                    latest_bundle_filename: uploadedFile.original_filename,
                });

                if (typeof onBundleUploaded === 'function') {
                    onBundleUploaded(uploadedFile);
                }

                return extension.save();
            }
        );
    }

    @action queueFile(file) {
        // since we have dropzone and upload button within dropzone validate the file state first
        // as this method can be called twice from both functions
        if (['queued', 'failed', 'timed_out', 'aborted'].indexOf(file.state) === -1) {
            return;
        }

        const { extension, onScreenshotUploaded } = this.args;

        // Queue and upload immediatley
        this.uploadQueue.pushObject(file);
        this.fetch.uploadFile.perform(
            file,
            {
                path: `uploads/extensions/${this.args.extension.id}/screenshots`,
                subject_uuid: this.args.extension.id,
                subject_type: 'registry-bridge:registry-extension',
                type: 'extension_screenshot',
            },
            (uploadedFile) => {
                extension.screenshots.pushObject(uploadedFile);
                this.uploadQueue.removeObject(file);
                if (typeof onScreenshotUploaded === 'function') {
                    onScreenshotUploaded(uploadedFile);
                }
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
}
