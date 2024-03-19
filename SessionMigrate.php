<?php
    
    namespace App\Console\Commands;
    
    use Illuminate\Console\Command;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\Auth\SessionGuard;
    use Illuminate\Support\Facades\DB;
    use Symfony\Component\Console\Helper\ProgressBar;
    
    class SessionMigrate extends Command
    {
        
        protected $signature = 'migrate:sessions';
    
        
        protected $description = 'Migrate sessions from files to database';
    
        protected $files;
        protected $table;
        protected $connection;
    
       
        public function __construct(Filesystem $files)
        {
            parent::__construct();
            $this->files = $files;
            $this->table = config('session.table');
            $this->connection = DB::connection(config('session.connection'));
        }
    
        /**
         * Execute the console command.
         *
         * @return int
         */
        public function handle()
        {
            $this->info("Obtain session files...");
            
            $dir = config('session.files');
            
            $sessions_count = $this->getSessionsCount($dir);
            
            if(FALSE == $dir_res = opendir($dir))
            {
                $this->error("Sessions directory open error.");
                return 1;
            }
            
            
            $lifetime = config('session.lifetime');
            
            $inserted_count = 0;
            $updated_count = 0;
            $errors = 0;
            
            $this->info('Found ' . $sessions_count . ' session files');
            
            $bar = $this->output->createProgressBar($sessions_count);
            $bar->start();
            
        
            while (FALSE !== ($file = readdir($dir_res)))
            {
                $file_path = $dir.'/'.$file;
                
                if (in_array($file, array('.', '..')) || is_dir($file_path))
                { 
                    continue;
                }
                
                $id = $file;
                
                try
                {
                    $content = file_get_contents($file_path);
                    
                    $data = @unserialize($content);
                        
                    if(is_array($data))
                    {
                        $login_name = 'login_web_' . sha1(SessionGuard::class);
                        $user_id = NULL;
                        
                        if(array_key_exists($login_name, $data))
                        {
                            $user_id = $data[$login_name];
                        }
                        
                        $exists = $this->checkExists($id);
                        $last_activity = filemtime($file_path);
                        
                        if($exists)
                        {
                            
                            $this->connection->table($this->table)->whereId($id)->update([
                                'user_id'   => $user_id,
                                'payload'   => base64_encode($content),
                                'last_activity' => $last_activity
                            ]);
                           
                            $updated_count++;
                        }
                        else
                        {   
                            
                            $this->connection->table($this->table)->insert([
                                'id'        => $id,
                                'user_id'   => $user_id,
                                'payload'   => base64_encode($content),
                                'last_activity' => $last_activity
                            ]);
                            
                            $inserted_count++;
                        }
                    
                    }
                }
                catch(Exception $e)
                {
                    $errors++;
                }
                                    
                $bar->advance();
            }
            closedir($dir_res);
            
            
            $bar->finish();
    
            
            $this->table(
                ['Total session files', 'Created', 'Updated', 'Errors'],
                [
                    [$sessions_count, $inserted_count, $updated_count, $errors]
                ]
            );
            
            return 0;
        }
        
        protected function checkExists($id)
        {
            return (bool)$this->connection->table($this->table)->whereId($id)->first();
        }
        
        protected function getSessionsCount(string $dir)
        {
            $result = 0;
            
            if(FALSE == $dir_res = opendir($dir))
            {
                $this->error("Directory open error. ($dir)");
                return 0;
            }
            
            while (($file = readdir($dir_res)) !== false)
            {
                if (!in_array($file, array('.', '..')) && !is_dir($dir.$file))
                { 
                    $result++;
                }
            }
            
            closedir($dir_res);
            
            return $result;
        }
    }