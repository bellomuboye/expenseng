<?php

namespace App\Console\Commands;


use App\MonthlyBudget;
use App\Payment;
use App\QuarterlyBudget;
use App\Report;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


class ParseSheet extends Command
{
    /**
     * declaring http.
     *
     * @var string
     */
    private $http;
    /**
     * uri for sending parse api.
     *
     * @var string
     */
    private $baseUri = "https://excel.microapi.dev/";
    
   

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'The cron job that parses downloaded sheets hourly';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->baseUri = "https://excel.microapi.dev/";
        
        $this->http = new Client([
            'base_uri' => $this->baseUri,
            'headers' => [
                'debug' => true,
                'Content-Type' => 'application/json',
            ]
        ]);
        

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        Log::info('Sheet parsing Cron is running');
        $this->info('Parsing Sheets ');
        try{
            $reports = Report::where('parsed', '!=', true)
                ->get();

                function getMonthBudget (){

                    if ( isset($response["JAN"])){
                        return $response["JAN"];
                    }else if (isset($response["FEB"])) {
                         return $response["FEB"];
                    }else if ( isset ($response["MAR"])) {
                        return $response["MAR"];
                    } else if (isset($response["APR"])) {
                        return $response["APR"];
                    } else if (isset($response["MAY"])){
                        return $response["MAY"];
                    } else if (isset($response["JUN"])) {
                        return $response["JUN"];
                    } else if (isset ($response["JUL"])) {
                        return $response["JUL"];
                    } else if (isset($response["AUG"])) {
                        return $response["AUG"];
                    } else if (isset($response["SEP"])) {
                        return $response["SEP"];
                    } else if (isset($response["OCT"])) {
                        return $response["OCT"];
                    } else if (isset($response["NOV"])) {
                        return $response["NOV"];
                    } else if (isset($response["DEC"])) {
                        return $response["DEC"];
                    } else {
                        return 0;
                    }
                      
                 }

                 function getQuarterBudget () {

                    if ( isset($response["1ST QUARTER"])){
                        return $response["1ST QUARTER"];
                    } else if (isset($response["2ND QUARTER"])) {
                        return $response["2ND QUARTER"];
                    } else if (isset($response["3RD QUARTER"])) {
                        return $response["3RD QUARTER"];
                    } else if (isset($response["4TH QUARTER"])) {
                        return $response["4TH QUARTER"];
                    } else {
                        return 0;
                    }
                      
                 }

            if (count ($reports ) > 0  ) {
                //consume api
                foreach ($reports as $report) {
                    try{
                        if ($report->type == 'DAILYPAYMENT' ) {
                            $this->info('Parsing:   ' . $report->link);

                            $date = basename($report->link, '.xlsx');
                          
                            $response = $this->http->post('api/', [

                                "body" => json_encode([
                                    "file_path" => 
                                    $report->link,
                                    "row_from"=> 15,
                                    "row_to" => 150,
                                    "API_KEY" => "random25stringsisneeded"
                                ])
                            ]);
                            $status = $response->getStatusCode();
                            $responses = json_decode($response->getBody(), true);
            
                    
                            if ($status == 200) {
                                foreach ($responses as $response) {
                                    

                                    $payment = new Payment();

                                    $payment->payment_no = $response["Payment No"];
                                    $payment->payment_code = $response["Payer Code"];
                                    $payment->organization = $response["ORGANIZATION NAME"];
                                    $payment->beneficiary = $response["Beneficiary Name"];
                                    $payment->amount = $response["Amount"];
                                    $payment->payment_date = $date;
                                    $payment->description = $response["Description"];
            
                                    $persist = $payment->save();
                                    if ($persist) {
                                        Report::where('id', $report->id)
                                        ->update(['parsed' => true]);
                                        $this->info($report->link .' Sheet parsed successfully');
                                    } else {
                                        $this->info('Persist Error:   '. $report->link . ' was not persisted');
                                    }
                                }

                            } else {
                                $this->info($report->link .' status not successful'); 
                            }
                        } else if ($report->type == 'MONTHLYBUDPERF') {
                            $this->info('Parsing:   ' . $report->link);
                            //do monthly parsing
                            $month = basename($report->link, 'xlsx');
                            $response = $this->http->post('api/', [

                                "body" => json_encode ([
                                    "file_path" => 
                                    $report->link,
                                    "row_from"=> 0,
                                    "row_to" => 15000,
                                    "API_KEY" => "random25stringsisneeded"
                                ])
                            ]);
                            $status = $response->getStatusCode();
                            $responses = json_decode($response->getBody(), true);

                            

            
                    
                            if ($status == 200) {
                                foreach ($responses as $response) {
                                    //print (isset($response["MAY"])? 'set' : 'not set');
                                   

                                    $monthly = new MonthlyBudget();
                                    $monthly->Name = $response["Name"];
                                    $monthly->code = $response["Code"];
                                    $monthly->year_payments_till_date = isset($response["PAYMENTS YTD"]) ? $response["PAYMENTS YTD"] : 0 ;
                                    $monthly->month = $month;
                                    $monthly->month_budget = getMonthBudget();
                                    $monthly->budget_amount = isset($response["BUDGET AMOUNT"]) ? $response["BUDGET AMOUNT"] : '' ;
                                    $monthly->budget_balance = isset($response["BUDGET BALANCE"]) ? $response["BUDGET BALANCE"] : '';
                                    $monthly->percentage = isset($response["PERCENTAGE"]) ? $response["PERCENTAGE"] : 0 ;
            
                                    $persist = $monthly->save();
                                    if ($persist) {
                                        Report::where('id', $report->id)
                                        ->update(['parsed' => true]);
                                        $this->info($report->link.' Monthly Sheet parsed successfully');
                                    } else {
                                        $this->info('Persist Error:   '. $report->link . ' was not persisted');
                                    }
                                }
                                

                            } else {
                                $this->info($report->link .' status not successful'); 
                            }
                        } else {
                            //do quarterly parsing

                            $this->info('Parsing:   ' . $report->link);

                            $quarter = basename($report->link, 'xslx');

                            $response = $this->http->post('api/', [

                                "body" => json_encode([
                                    "file_path" => 
                                    $report->link,
                                    "row_from"=> 15,
                                    "row_to" => 15000,
                                    "API_KEY" => "random25stringsisneeded"
                                ])
                            ]);
                            $status = $response->getStatusCode();
                            $responses = json_decode($response->getBody(), true);
                        
                    
                    
            
                            
                            if ($status == 200) {
                                foreach ($responses as $response) {
                                   
                                    //return print_r ($response);
                                    $quarterly = new QuarterlyBudget();
                                    $quarterly->Name = $response["Name"];
                                    $quarterly->code = $response["Code"];
                                    $quarterly->year_payments_till_date = isset($response["PAYMENTS YTD"]) ? $response["PAYMENTS YTD"] : 0 ;;
                                    $quarterly->quarter = $quarter;
                                    $quarterly->quarter_budget = getQuarterBudget();
                                    $quarterly->budget_amount = isset($response["BUDGET AMOUNT"]) ? $response["BUDGET AMOUNT"] : '';
                                    $quarterly->budget_balance =  isset($response["BUDGET BALANCE"]) ? $response["BUDGET BALANCE"] : '';
                                    $quarterly->percentage = isset($response["PERCENTAGE"]) ? $response["PERCENTAGE"] : 0.00 ;
            
                                    $persist = $quarterly->save();
                                    if ($persist) {
                                        Report::where('id', $report->id)
                                        ->update(['parsed' => true]);
                                        $this->info($report->link.' Quarterly Sheet parsed successfully');
                                    } else {
                                        $this->info('Persist Error:   '. $report->link . ' was not persisted');
                                    }
                                }
                                
                            } else {
                                $this->info($report->link .' status not successful'); 
                            }
                        }
                    } catch (Exception $e) {
                        $this->error($e->getMessage());
                        $this->info($report->link .' Sheet not parsed');
                    }
                }
                $this->info('All Sheets parsed successfully');
            }


        }catch (Exception $e) {
            $this->info($e->getMessage() .' Sheet cannot be run');
        }

        //return 0;
    }
}
