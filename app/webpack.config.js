const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .addEntry('admin_styles', './assets/styles/admin.css')
    .addEntry('admin', './assets/js/admin.js')
    .addEntry('dashboard', './assets/css/dashboard.css')
    .addEntry('dashboard_js', './assets/js/pages/dashboard.js')
    .addEntry('base', './assets/css/base.css')
    .addEntry('base_js', './assets/js/base.js')
    .addEntry('client_forms', './assets/js/pages/client-forms.js')
    .addEntry('subscription_forms', './assets/js/pages/subscription-forms.js')
    .addEntry('logs', './assets/css/logs.css')
    .addEntry('login', './assets/css/login.css')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSassLoader()
    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
;

module.exports = Encore.getWebpackConfig();