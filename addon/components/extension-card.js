import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class ExtensionCardComponent extends Component {
    @service modalsManager;
    @service notifications;
    @service currentUser;
    @service socket;
    @tracked extension;

    constructor(owner, { extension }) {
        super(...arguments);
        this.extension = extension;
    }

    @action onClick() {
        const installChannel = `install.${this.currentUser.companyId}.${this.extension.id}`;

        if (typeof this.args.onClick === 'function') {
            this.args.onClick(this.extension);
        }

        this.modalsManager.show('modals/extension-details', {
            titleComponent: 'extension-modal-title',
            modalClass: 'flb--extension-modal modal-lg',
            modalHeaderClass: 'flb--extension-modal-header',
            acceptButtonText: 'Install',
            acceptButtonIcon: 'download',
            declineButtonText: 'Done',
            progress: 0,
            extension: this.extension,
            confirm: async (modal) => {
                modal.startLoading();

                // Listen for install progress
                this.socket.listen(installChannel, ({ progress }) => {
                    modal.setOption('progress', progress);
                });

                // Start install progress
                modal.setOption('progress', 5);

                // Run install
                try {
                    await this.extension.install();
                    this.notifications.info(`${this.extension.name} is now Installed.`);
                    modal.done();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }
}
