import buildRoutes from 'ember-engines/routes';

export default buildRoutes(function () {
    this.route('developers', function () {
        this.route('extensions', function () {
            this.route('index', { path: '/' });
            this.route('new');
            this.route('edit', { path: '/edit/:public_id' });
            this.route('details', { path: '/:public_id' });
        });
        this.route('analytics');
        this.route('payments');
        this.route('credentials');
    });
    this.route('explore', function () {
        this.route('index', { path: '/' });
        this.route('category', { path: '/:category' });
    });
});
