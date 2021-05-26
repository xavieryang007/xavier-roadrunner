<?php

namespace xavier\roadrunner\command;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\helper\Str;

class RoadRunnerStartCommand extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('xavier:roadrunner')
            ->addArgument("run")
            ->addOption('host', null, Option::VALUE_OPTIONAL, 'The IP address the server should bind to', '127.0.0.1')
            ->addOption('port', null, Option::VALUE_OPTIONAL, 'The port the server should be available on', 8882)
            ->addOption('workers', null, Option::VALUE_OPTIONAL, 'The number of workers that should be available to handle requests', 'auto')
            ->addOption('max-requests', null, Option::VALUE_OPTIONAL, 'The number of requests to process before reloading the server', 500)
            ->setDescription('Start the Thinkphp RoadRunner server');
    }

    protected function execute(Input $input, Output $output)
    {
        $run = $input->getArgument('run');
        if ($run == 'stop') {
            $this->stop($input, $output);
        } else if ($run == 'reload') {
            $this->reload($input, $output);
        } else {
            $this->start($input, $output);
        }
    }

    protected function stop(Input $input, Output $output)
    {

    }

    protected function reload(Input $input, Output $output)
    {

    }

    protected function start(Input $input, Output $output)
    {
        $roadRunnerBinary = $this->ensureRoadRunnerBinaryIsInstalled($input, $output);

        // 指令输出
        $server = new Process(array_filter([
            $roadRunnerBinary,
            '-c', root_path('.rr.yaml'),
            '-o', 'http.address=' . $input->getOption('host') . ':' . $input->getOption('port'),
            '-o', 'server.command=' . (new PhpExecutableFinder)->find() . ' ./vendor/bin/roadrunner-worker',
            '-o', 'http.pool.num_workers=' . $this->workerCount($input),
            '-o', 'http.pool.max_jobs=' . $input->getOption('max-requests'),
            '-o', 'http.pool.supervisor.exec_ttl=' . $this->maxExecutionTime(),
            '-o', 'http.static.dir=public',
            '-o', 'http.middleware=static',
            '-o', env('local.mode') == 'production' ? 'logs.mode=production' : 'logs.mode=none',
            '-o', env('local.level') == 'production' ? 'logs.level=debug' : 'logs.level=warning',
            '-o', 'logs.output=stdout',
            '-o', 'logs.encoding=json',
            'serve',
        ]), root_path());
        $server->start();

        $this->writeServerRunningMessage($input, $output);
        //等待进程启动
        while (!$server->isStarted()) {
            sleep(1);
        }

        while ($server->isRunning()) {
            usleep(500);
        }
    }

    /**
     * Write the server start "message" to the console.
     *
     * @return void
     */
    protected function writeServerRunningMessage(Input $input, Output $output)
    {
        $output->info('Server running…');

        $output->writeln(join(PHP_EOL, [
            '',
            '  Local: <fg=white;options=bold>http://' . $input->getOption('host') . ':' . $input->getOption('port') . ' </>',
            '',
            '  <fg=yellow>Press Ctrl+C to stop the server</>',
            '',
        ]));
    }

    protected function workerCount(Input $input)
    {
        return $input->getOption('workers') == 'auto' ? 0 : $input->getOption('workers');
    }

    protected function maxExecutionTime()
    {
        return config('roadrunner.max_execution_time', '300') . 's';
    }

    /**
     * Ensure the RoadRunner binary is installed into the project.
     *
     * @return string
     */
    protected function ensureRoadRunnerBinaryIsInstalled(Input $input, Output $output): string
    {
        if (file_exists(root_path() . 'rr')) {
            return root_path() . 'rr';
        }

        if (!is_null($roadRunnerBinary = (new ExecutableFinder)->find('rr', null, [root_path()]))) {
            if (!Str::contains($roadRunnerBinary, 'vendor/bin/rr')) {
                return $roadRunnerBinary;
            }
        }
        if ($output->confirm($input, 'Xavier:Roadrunner requires "spiral/roadrunner:^2.0". Do you wish to install it as a dependency?')) {
            $this->downloadRoadRunnerBinary();
            copy(__DIR__ . '/../../stubs/rr.yaml', root_path() . '.rr.yaml');
        }

        return root_path() . 'rr';
    }

    /**
     * Download the latest version of the RoadRunner binary.
     *
     * @return void
     */
    protected function downloadRoadRunnerBinary()
    {
        $bin = './vendor/bin/rr';
        if ("\\" === \DIRECTORY_SEPARATOR) {
            $bin = './vendor/bin/rr.bat';
        }
        $process = new Process(array_filter([
            $bin,
            'get-binary',
            '-n',
            '--ansi',
        ]), root_path(), null, null, null);
        $process->mustRun(
            fn($type, $buffer) => $this->output->write($buffer)
        );

        if ("\\" !== \DIRECTORY_SEPARATOR) {
            chmod(root_path() . 'rr', 755);
        }
    }
}