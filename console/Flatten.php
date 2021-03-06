<?php

namespace JaxWilko\DataMigrator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JaxWilko\DataMigrator\Classes\Utils;
use JaxWilko\DataMigrator\Models\Settings;
use League\Csv\Writer;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class Flatten extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'data:flat';

    /**
     * @var string The console command description.
     */
    protected $description = 'Update flat files';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        if ($this->option('table')) {
            if (!Settings::tableExists($this->option('table'))) {
                throw new \InvalidArgumentException(sprintf('table `%s` not found', $this->option('table')));
            }
            $this->updateFlatFile($this->option('table'));
            return;
        }

        foreach (Settings::get('tables') as $table) {
            $this->updateFlatFile($table);
        }
    }

    protected function updateFlatFile(string $table)
    {
        $data = DB::table($table)->get();

        if (count($data) < 1) {
            $this->warn(sprintf('no data in table: `%s`', $table));
            return false;
        }

        $path = storage_path(Settings::get('storage', 'datamigrator'));
        $filePath = sprintf('%s%s%s.csv', $path, DIRECTORY_SEPARATOR, $table);
        
        if (!is_dir($path)) {
            $this->info('storage directory not found, creating now');
            mkdir($path, 0755);
        }

        $csv = Writer::createFromPath($filePath, 'w');

        $headings = array_keys((array) $data[0]);
        $csv->insertOne($headings);

        foreach ($data as $record) {
            $record = (array) $record;
            foreach ($record as $index => $value) {
                if (is_array($value) || is_object($value)) {
                    $record[$index] = json_encode($value);
                }
            }
            $csv->insertOne(array_values($record));
        }

        $csv = null;

        Utils::filePrepend($filePath, sprintf('hash,%s%s', sha1_file($filePath), PHP_EOL));

        $this->info(sprintf('%s written', basename($filePath)));

        return true;
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['table', 't', InputOption::VALUE_OPTIONAL, 'Table to convert into flat file.', null]
        ];
    }
}
