import Controller from '@ember/controller';
import { tracked } from '@glimmer/tracking';

export default class DevelopersPaymentsIndexController extends Controller {
    @tracked hasStripeConnectAccount = true;
}
