import Route from '@ember/routing/route';
import { tracked } from '@glimmer/tracking';

export default class ExploreCategoryRoute extends Route {
    @tracked category;

    model(params) {
        this.category = params.category;
    }

    setupController(controller) {
        super.setupController(...arguments);
        controller.category = this.category;
    }
}
