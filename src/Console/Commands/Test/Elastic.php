<?php

namespace Core\Console\Commands\Test;
use Illuminate\Console\Command;
use Elasticsearch;
class Elastic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:elastic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Elastic Search';

    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $data = [
            'body' => [
                'testField' => 'abc'
            ],
            'index' => 'my_index',
            'type' => 'my_type',
            'id' => 'my_i@d',
        ];

        $return = Elasticsearch::index($data);
        sleep(1);
        unset($data['body']);
        $response = Elasticsearch::get($data);
        print_r($response);
        $params = [
            'index' => 'my_index',
            'type' => 'my_type',
            'body' => [
                'query' => [
                    'match' => [
                        'testField' => 'abc'
                    ]
                ]
            ]
        ];
        
        $response = Elasticsearch::search($params);
        print_r(Elasticsearch::indices()->delete(['index' => 'my_index']));
        //print_r(Elasticsearch::indices()->delete(['index' => 'geol']));
        $index = Elasticsearch::indices()->create(['index' => 'geol',
        "body"=>["mappings"=> [
            "my_type"=> [
              "properties"=> [
                "location"=> [
                  "type"=> "geo_point"
                ]
              ]
            ]
          ]]]);


          $data = [
            'body' => [
                'location' => ['lat'=>41.12, 'lon'=> -71.34]
            ],
            'index' => 'geol',
            'type' => 'my_type',
            'id' => '1@d',
        ];

        $return = Elasticsearch::index($data);
        sleep(1);
        unset($data['body']);
        $response = Elasticsearch::get($data);
          print_r($response);




          $params = [
            'index' => 'geol',
            'type' => 'my_type',
            'body' => [
                'query' => [
                    'geo_bounding_box' => [
                        "location"=> [
                            "top_left"=> [
                              "lat"=> 42,
                              "lon"=> -72
                            ],
                            "bottom_right"=> [
                              "lat"=> 40,
                              "lon"=> -74
                            ]
                            ]
                    ]
                ]
            ]
        ];
        
        $response = Elasticsearch::search($params);


        print_r(Elasticsearch::indices()->delete(['index' => 'geol']));

        dd($response);
    }
   
}
