<?php
namespace OCA\Video_Converter_Fm\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version100600Date20250129000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('video_jobs')) {
            $table = $schema->createTable('video_jobs');
            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('file_id', 'string', [
                'notnull' => true,
                'length' => 1024,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => false,
                'length' => 64,
            ]);
            $table->addColumn('status', 'string', [
                'notnull' => true,
                'length' => 20,
                'default' => 'pending',
            ]);
            $table->addColumn('input_path', 'string', [
                'notnull' => false,
                'length' => 1024,
            ]);
            $table->addColumn('output_formats', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
            ]);
            $table->addColumn('started_at', 'datetime', [
                'notnull' => false,
            ]);
            $table->addColumn('finished_at', 'datetime', [
                'notnull' => false,
            ]);
            $table->addColumn('progress', 'integer', [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('retry_count', 'integer', [
                'notnull' => true,
                'default' => 0,
            ]);
            $table->addColumn('error_message', 'text', [
                'notnull' => false,
            ]);
            $table->addColumn('worker_host', 'string', [
                'notnull' => false,
                'length' => 128,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['status'], 'idx_video_jobs_status');
            $table->addIndex(['user_id'], 'idx_video_jobs_user');
        }

        return $schema;
    }
}