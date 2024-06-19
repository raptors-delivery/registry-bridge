import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class InstalledController extends Controller {
    @service modalsManager;
    @service currentUser;
    @service notifications;
    @service socket;
    @service hostRouter;

    @action about(extension) {
        this.modalsManager.show('modals/extension-details', {
            titleComponent: 'extension-modal-title',
            modalClass: 'flb--extension-modal modal-lg',
            modalHeaderClass: 'flb--extension-modal-header',
            acceptButtonText: 'Done',
            hideDeclineButton: true,
            extension,
        });
    }

    @action uninstall(extension) {
        const uninstallChannel = `uninstall.${this.currentUser.companyId}.${extension.id}`;

        this.modalsManager.show('modals/extension-uninstall', {
            title: `Uninstall ${extension.name}`,
            modalClass: 'flb--extension-modal modal-lg',
            modalHeaderClass: 'flb--extension-modal-header',
            acceptButtonText: 'Uninstall',
            acceptButtonIcon: 'trash',
            acceptButtonScheme: 'danger',
            progress: 0,
            extension,
            confirm: async (modal) => {
                modal.startLoading();

                // Listen for install progress
                this.socket.listen(uninstallChannel, ({ progress }) => {
                    modal.setOption('progress', progress);
                });

                // Start uninstall progress
                modal.setOption('progress', 5);

                // Run uninstall
                try {
                    await extension.uninstall();
                    await this.hostRouter.refresh();
                    this.notifications.info(`${extension.name} is now Uninstalled.`);
                    modal.done();
                } catch (error) {
                    this.notifications.serverError(error);
                }
            },
        });
    }
}
