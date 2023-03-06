<?php


class Blog {

    protected $connection;

    public function __construct($config)
    {
        $factory = new React\MySQL\Factory();
        return $this->connection = $factory->createLazyConnection($config);
    }

    public function create($slug, $content, $isSelf = 1)
    {
        return $this->connection->query('INSERT INTO blog (slug,content,is_self) values(?,?,?)', [$slug, $content, $isSelf])->then(function($command){
            if (isset($command->resultRows)) {
                // this is a response to a SELECT etc. with some rows (0+)
                // print_r($command->resultFields);
                // print_r($command->resultRows);
                echo count($command->resultRows) . ' row(s) in set' . PHP_EOL;
            } else {
                // this is an OK message in response to an UPDATE etc.
                if ($command->insertId !== 0) {
                    var_dump('last insert ID', $command->insertId);
                }
                echo 'Query OK, ' . $command->affectedRows . ' row(s) affected' . PHP_EOL;
            }
        });
    }

    public function getLists()
    {
        return $this->connection->query("select * from blog where id in (select SUBSTRING_INDEX( GROUP_CONCAT( id ORDER BY created_at ASC ), ',', 1 ) AS last_id from blog group by slug ) order by id desc");
    }

    public function show($slug)
    {
        return $this->connection->query("select * from blog where slug = ? order by created_at ASC", [$slug]);
    }
}