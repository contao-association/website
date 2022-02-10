var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/layout/')
    .setPublicPath('/layout')
    .setManifestKeyPrefix('')
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()

    .addEntry('app', './layout/scripts/app.js')

    .copyFiles({
        from: './layout/icons',
        pattern: /\.(png|svg|ico)$/i,
        to: 'icons/[path][name].[hash:8].[ext]'
    })

    // will require minified scripts without packing them
    .addLoader({
        test: /\.min\.js$/,
        use: [ 'script-loader' ]
    })

    // optimize and minify images
    .addLoader({
        test: /\.(gif|png|jpe?g|svg)$/i,
        use: [ 'image-webpack-loader' ]
    })

    .enableSassLoader()
    .enablePostCssLoader()
    .enableSourceMaps()
    .enableVersioning()
;

// export the final configuration
module.exports = Encore.getWebpackConfig();
