<?php
class DbPDO extends PDO
{
    public function __construct($database, $file = 'database.ini')
    {
        if (!$settings = parse_ini_file($file, TRUE)) throw new exception('Não abriu o arquivo de configuração ' . $file . '.');
        	// monta a string $dns para o PDO
            if (substr($settings[$database]['driver'],0,4)=='odbc') {
                $dns = $settings[$database]['driver'];
            } else {
                $dns = $settings[$database]['driver'] .
                ':host=' . $settings[$database]['host'] .
                ((isset($settings[$database]['port'])) ? (';port=' . $settings[$database]['port']) : '') .
                ';dbname=' . $settings[$database]['basename'];        
            }
        try {
			parent::__construct($dns, $settings[$database]['username'], $settings[$database]['password']);
	    	$this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die("Erro de conexão(PDO): " . $e->getMessage());
		}
    }
}
?>
