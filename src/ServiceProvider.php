<?php namespace Barryvdh\Form;

use Barryvdh\Form\Extension\SessionExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Barryvdh\Form\Extension\EloquentExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Barryvdh\Form\Extension\FormValidatorExtension;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;

class ServiceProvider extends BaseServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function boot()
    {
        $configPath = __DIR__ . '/../config/form.php';
        $this->publishes([$configPath => config_path('form.php')], 'config');

        // Add the Form templates to the Twig Chain Loader
        $reflected = new \ReflectionClass(FormExtension::class);
        $path = dirname($reflected->getFileName()).'/../Resources/views/Form';
        $this->app['twig.loader']->addLoader(new \Twig_Loader_Filesystem($path));

        $this->app['twig']->addExtension(new FormExtension($this->app['twig.form.renderer']));
        $this->app['twig']->addFilter(new \Twig_SimpleFilter('trans', 'trans'));
        $this->app['twig']->addFunction(new \Twig_SimpleFunction('csrf_token', 'csrf_token'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/../config/form.php';
        $this->mergeConfigFrom($configPath, 'form');

        $this->app->bind('twig.form.engine', function ($app) {
            $theme = (array) $app['config']->get('form.theme', 'bootstrap_3_layout.html.twig');
            return new TwigRendererEngine($theme, $app['twig']);
        });

        $this->app->bind('twig.form.renderer', function ($app) {
            return new TwigRenderer($app['twig.form.engine']);
        });

        $this->app->bind('form.types', function () {
            return array();
        });

        $this->app->bind('form.type.extensions', function () {
            return array();
        });

        $this->app->bind('form.type.guessers', function () {
            return array();
        });

        $this->app->bind('form.extensions', function ($app) {
            return array(
                $app->make(SessionExtension::class),
                new HttpFoundationExtension(),
                new EloquentExtension(),
                new FormValidatorExtension()
            );
        });

        $this->app->bind('form.factory', function($app) {
            return Forms::createFormFactoryBuilder()
                ->addExtensions($app['form.extensions'])
                ->addTypes($app['form.types'])
                ->addTypeExtensions($app['form.type.extensions'])
                ->addTypeGuessers($app['form.type.guessers'])
                ->setResolvedTypeFactory($app['form.resolved_type_factory'])
                ->getFormFactory();
        });
        $this->app->alias('form.factory', FormFactoryInterface::class);

        $this->app->bind('form.resolved_type_factory', function () {
            return new ResolvedFormTypeFactory();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            FormFactoryInterface::class,
            'form.factory',
            'twig.form.engine',
            'twig.form.renderer',
            'form.resolved_type_factory',
            'form.types',
            'form.type.extensions',
            'form.type.guessers',
            'form.extensions',
        );
    }
}
