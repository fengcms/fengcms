<?php
final class CmsDatabaseGateBusy extends RuntimeException {}
/** Serializes a restore/backup with all CMS writes, including MyISAM and DDL. */
final class CmsDatabaseGate
{
    private const SLOTS=8;
    private static ?mysqli $connection=null;
    private static array $held=[];
    private static int $depth=0;
    private static bool $barrier=false;

    private static function connection(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db=mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT,5);
        $db->real_connect(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    private static function key(int $slot): string
    {
        return 'fengcms_gate_'.substr(hash('sha256',DB_NAME),0,40).'_'.$slot;
    }

    private static function acquire(mysqli $db,int $slot,float $seconds): void
    {
        $got=$db->execute_query('SELECT GET_LOCK(?,?)',[self::key($slot),$seconds])->fetch_row();
        if((int)($got[0]??0)!==1){
            throw new CmsDatabaseGateBusy('数据库正在备份、恢复或处理其他写入，请稍后重试。');
        }
        self::$held[]=$slot;
    }

    private static function release(): void
    {
        $db=self::$connection;
        if($db){
            foreach(array_reverse(self::$held) as $slot){
                try{$db->execute_query('SELECT RELEASE_LOCK(?)',[self::key($slot)]);}
                catch(Throwable $e){error_log('Database gate release failed: '.$e->getMessage());}
            }
            $db->close();
        }
        self::$connection=null;self::$held=[];self::$barrier=false;
    }

    public static function held(): bool {return self::$depth>0;}

    public static function write(callable $work): mixed
    {
        if(self::$depth>0){self::$depth++;try{return $work();}finally{self::$depth--;}}
        self::$connection=self::connection();
        self::$depth=1;
        try{
            self::acquire(self::$connection,random_int(0,self::SLOTS-1),5.0);
            return $work();
        }finally{self::$depth=0;self::release();}
    }

    public static function barrier(callable $work): mixed
    {
        if(self::$barrier){self::$depth++;try{return $work();}finally{self::$depth--;}}
        if(self::$depth>0){throw new LogicException('Database barrier must precede writer locks.');}
        self::$connection=self::connection();self::$depth=1;
        $deadline=microtime(true)+10.0;
        try{
            for($slot=0;$slot<self::SLOTS;$slot++){
                $remaining=$deadline-microtime(true);
                if($remaining<=0){throw new CmsDatabaseGateBusy('数据库写入尚未结束，请稍后重试。');}
                self::acquire(self::$connection,$slot,min(2.0,$remaining));
            }
            self::$barrier=true;
            return $work();
        }finally{self::$depth=0;self::release();}
    }
}
