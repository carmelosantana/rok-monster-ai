<?php

declare(strict_types=1);

namespace RoKMonster\AI;

use carmelosantana\ADB\ADB;
use RoKMonster\AI\RoK;
use RoKMonster\AI\Server;
use Colors\Color;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;

/**
 * RoK Accessibility Assistant
 */
class Agent
{
    protected $action;

    protected $adb;

    protected $app = [
        'title' => 'RoK Monster Assistant',
        'process' => 'rokmonster-cli',
        'version' => '0.1.0',
    ];

    protected $config = [
        'debug' => true,
        'distance' => 50,
        'fingerprint' => true
    ];

    protected $halt = false;

    protected $state = [];

    public function __construct()
    {
        $this->var = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var';

        $this->initApp();
        $this->initProcess();
    }

    private function initADB()
    {
        $this->adb = (new ADB())->bin('adb.exe');

        if (!$this->adbConnected())
            $this->adb->startServer();
    }

    private function initApp()
    {
        $this->rok = new RoK();

        // $this->initEnv();

        $this->initADB();
    }

    private function initDebug()
    {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }

    private function initEnv()
    {
        $env = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        // setup env if known exists
        if (!is_file($env)) {
            $sample = $env . '.example';
            copy($sample, $env);
        }

        $this->dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $this->dotenv->safeLoad();
    }

    private function initProcess()
    {
        $this->pid = $this->config('server') ? pcntl_fork() : getmypid();

        if ($this->pid == -1) {
            die('Error: pcntl_fork()');
        } elseif ($this->pid) {
            if (php_sapi_name() == "cli")
                $this->cliStart();

            // pcntl_wait($status); //Protect against Zombie children
        } else {
            (new Server());
        }
    }

    public function action($action)
    {
        // can we do this?
        if (!$this->adbConnected()) {
            $this->halt = true;
            \cli\err('Please connect a device.');
            die;
        }

        // load action
        $this->action = $this->rok->actions($action);

        // iterate through each input step
        array_walk($this->action['input'], [$this, 'adbIterateInput']);

        // reset states and halts
        $this->stateReset();
    }

    public function fingerprint(string $path)
    {
        $this->hasher = new ImageHash(new DifferenceHash());

        return $this->hasher->hash($path);
    }

    public function config($option, $alt = false)
    {
        return $this->config[$option] ?? $_ENV[strtoupper($option)] ?? $alt;
    }

    public function configSet($option, $value)
    {
        return $this->config[$option] = $value;
    }

    public function adbConnected()
    {
        if (!empty($this->adb->devices()))
            return true;

        return false;
    }

    public function adbIterateInput($input)
    {
        // skip any further action
        if ($this->halt)
            return;

        // TODO: add random +- to inputs
        // TODO: add sleep
        $this->adbScreencap();

        // check if fingerprint is close
        // $this->adb->input($input['type'] ?? 'tap', $input['args']);
        $default = [
            'type' => 'tap',
            'fingerprint' => null,
            'sleep' => $this->config('sleep', 0),
        ];

        $input = array_merge($default, $input);

        if ($this->config('fingerprint') and $input['fingerprint']) {
            $this->stateSet('distance', $this->hasher->distance($input['fingerprint'], $this->state('fingerprint')));
            $this->stateSet('target', $input['fingerprint']);

            if ($this->state('distance') >= $this->config('distance')) {
                $this->halt = true;
                \cli\err('Unknown location.');
            }
        }

        if (!$this->halt) {
            if ($input['sleep'] > 0)
                sleep((int) $input['sleep']);

            $this->adb->input($input['type'], $input['args']);
        }

        $this->cliOutputStatus();
    }

    public function adbScreencap(string $path = null)
    {
        if (!$path)
            $path = $this->adbScreencapPath();

        $filesize = $this->adb->screencap($path);

        if ($this->config('fingerprint'))
            $this->stateSet('fingerprint', $this->fingerprint($path));

        return $this->state('fingerprint');
    }

    public function adbScreencapPath($filename = 'screen.png')
    {
        return $this->var . DIRECTORY_SEPARATOR . $filename;
    }

    public function cliOutputStatus()
    {
        $c = new Color();

        $output = [];
        $output[] = $c('Status')->center();
        if ($this->halt)
            $output[] = $c('Halt: TRUE')->white()->bold()->highlight('red');

        if ($this->config('debug'))
            var_dump($this->state);

        self::replaceCommandOutput($output);
    }

    public function cliStart()
    {
        $menu = (new CliMenuBuilder)
            ->setTitle($this->app['title'])
            ->addLineBreak()
            ->addStaticItem('[Actions]')
            ->addSubMenu('Gather resources', function (CliMenuBuilder $menu_farming) {
                $menu_farming->setTitle($this->app['title'] . ' > Farming')
                    ->addItem('Cropland', function () {
                        $this->action('gather-resource-cropland');
                    })
                    ->addLineBreak('-');
            })
            ->addLineBreak()
            ->addStaticItem('[Tools]')
            ->addSubMenu('ADB', function (CliMenuBuilder $menu_adb) {
                $menu_adb->setTitle($this->app['title'] . ' > ADB')
                    ->addItem('Devices', function (CliMenu $menu) {
                        $output = $this->adbConnected() ? implode(PHP_EOL, $this->adb->devices()) : 'None';

                        $flash = $menu->flash($output);
                        $flash->getStyle()->setBg('231');
                        $flash->getStyle()->setFg('black');
                        $flash->display();
                    })
                    ->addItem('Screencap', function (CliMenu $menu) {
                        $this->adbScreencap($this->adbScreencapPath((string) time() . '.png'));
                    })
                    ->addItem('Start server', function (CliMenu $menu) {
                        $this->adb->startServer();
                    })
                    ->addItem('Kill server', function (CliMenu $menu) {
                        $this->adb->killServer();
                    });
            })
            ->addItem('Fingerprint scene', function (CliMenu $menu) {
                $flash = $menu->flash($this->adbScreencap());
                $flash->getStyle()->setBg('231');
                $flash->getStyle()->setFg('black');
                $flash->display();
            })
            ->addSubMenu('Options', function (CliMenuBuilder $d) {
                $d->setTitle($this->app['title'] . ' > Options')
                    ->addItem('First option', function (CliMenu $menu) {
                        echo sprintf('Executing option: %s', $menu->getSelectedItem()->getText());
                    })
                    ->addLineBreak('-');
            })
            ->addLineBreak()
            ->addStaticItem('[Help]')
            ->addSubMenu('Debug', function (CliMenuBuilder $menu_debug) {
                $menu_debug->setTitle($this->app['title'] . ' > Debug')
                    ->addLineBreak('-');
            })
            ->addLineBreak()
            ->addLineBreak('-')
            ->addLineBreak()
            ->setWidth(72)
            ->setBackgroundColour('237')
            ->setForegroundColour('156')
            ->setBorder(0, 0, 0, 2, ($this->adbConnected() ? '156' : '165'))
            ->setPadding(2, 5)
            ->setMargin(3)
            ->build();

        $menu->open();
    }

    public function state($option, $alt = false)
    {
        return $this->state[$option] ?? $alt;
    }

    public function stateSet($option, $value)
    {
        return $this->state[$option] = $value;
    }

    public function stateUpdate()
    {
        $this->state['time'] = gmdate('r');
        $this->state['action'] = $this->action['name'];
    }

    public function stateReset()
    {
        $this->halt = false;
        $this->state = [];
    }

    static private function echo($output)
    {
        fwrite(STDOUT, (string) $output . "\r\n");
    }

    // https://www.hashbangcode.com/article/overwriting-command-line-output-php
    static private function replaceCommandOutput(array $output)
    {
        static $oldLines = 0;
        $numNewLines = count($output) - 1;

        if ($oldLines == 0) {
            $oldLines = $numNewLines;
        }

        echo implode(PHP_EOL, $output);
        echo chr(27) . "[0G";
        echo chr(27) . "[" . $oldLines . "A";

        $numNewLines = $oldLines;
    }
}
