/* eslint-disable @typescript-eslint/no-require-imports */
const Encore = require('@symfony/webpack-encore') as any

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV ?? 'dev')
}

Encore
    .setOutputPath('src/Resources/public/build/')
    .setPublicPath('/bundles/menubuilder/build/')
    .setManifestKeyPrefix('build/')
    .addEntry('menu_builder', './assets/main.ts')
    .enableVueLoader(() => {}, { version: 3 })
    .enableTypeScriptLoader((tsConfig: Record<string, unknown>) => {
        tsConfig['appendTsSuffixTo'] = [/\.vue$/]
    })
    .addAliases({ '@': '/app/assets/' })
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

module.exports = Encore.getWebpackConfig()
