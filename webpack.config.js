const Encore = require('@terminal42/contao-build-tools');

module.exports = Encore()

    .copyFiles({
        from: './layout/icons',
        to: 'icons/[name].[ext]'
    })

    .getWebpackConfig()
;
