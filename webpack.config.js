const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/layout/')
    .setPublicPath('/layout')
    .setManifestKeyPrefix('')
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()

    .enableSassLoader()
    .enablePostCssLoader()
    .enableSourceMaps()
    .enableVersioning(Encore.isProduction())

    .addEntry('app', './layout/app.js')

    .copyFiles({
        from: './layout/icons',
        to: 'icons/[name].[ext]'
    })

    .addLoader({
        test: /\.(gif|png|jpe?g|svg)$/i,
        use: ['image-webpack-loader']
    })

    .configureDevServerOptions(() => ({
        allowedHosts: 'all',
        watchFiles: ['config/*', 'contao/**/*', 'src/**/*', 'templates/**/*', 'translations/**/*'],
    }))
;

module.exports = Encore.getWebpackConfig();
