<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Support\Enums\Fees;
use App\Support\Enums\Limits;
use App\Support\Enums\Operations;
use App\Support\Enums\UserType;
use App\Support\Utils\Exchange;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    public function index()
    {
        return view('web.index');
    }

    public function calculate(Request $request)
    {
        //check the file if exist
        if ($request->hasFile('file')) {

            //check the file if we can open
            if (($file = fopen($_FILES["file"]["tmp_name"], "r")) !== FALSE) {
                $dates = array();
                $rows = array();

                //read the lines
                while (!feof($file)) {
                    //take the lines
                    $row = fgetcsv($file, '', ',', '\\');
                    array_push($rows, $row);
                }

                //close the file
                fclose($file);

                //start calculation one by one
                foreach ($rows as $key => $value) {
                    echo "<p>";
                    //operation type
                    switch ($value[3]) {
                        case Operations::DEPOSIT:
                            echo Helper::roundUp($value[4] * Fees::DEPOSIT_FEE);
                            break;
                        case Operations::WITHDRAW:
                            //user type
                            switch ($value[2]) {
                                case UserType::BUSSINESS:
                                    echo Helper::roundUp($value[4] * Fees::BUSSINESS_WITHDRAW);
                                    break;
                                case UserType::PRIV:
                                    //translate to euro
                                    $euroAmount = Helper::exchange($value[4], $value[5]);

                                    if ($euroAmount < 0) {
                                        echo "Not Found";
                                        break;
                                    }

                                    //calculate euro limit by currency
                                    $euroLimitByCurrency = Helper::withdrawLimit($value[5]);

                                    //check if we calculate any line for this user before
                                    if (!isset($dates[$value[1]])) {
                                        //check if more than withdraw limit
                                        if ($value[4] > $euroLimitByCurrency)
                                            echo Helper::roundUp(($value[4] - $euroLimitByCurrency) * Fees::PRIV_WITHDRAW);
                                        else
                                            echo Helper::roundUp(0);

                                        //add to array user last withdraw info
                                        $dates[$value[1]] = [
                                            "time" => 1,
                                            "week_begin" => date("Y-m-d", strtotime('monday this week', strtotime($value[0]))),
                                            "week_end" => date("Y-m-d", strtotime('sunday this week', strtotime($value[0]))),
                                            "amount" =>  $euroAmount
                                        ];
                                    } else {
                                        //check if same week
                                        if ($value[0] >= $dates[$value[1]]["week_begin"] && $value[0] <= $dates[$value[1]]["week_end"]) {
                                            //check if less then 3 times
                                            if ($dates[$value[1]]["time"] > 2)
                                                echo Helper::roundUp($value[4] * Fees::PRIV_WITHDRAW);
                                            else {
                                                //check if more than withdraw limit
                                                if ($dates[$value[1]]["amount"] > Limits::WITHDRAW_LIMIT)
                                                    echo Helper::roundUp($value[4] * Fees::PRIV_WITHDRAW);
                                                else if ($dates[$value[1]]["amount"] + $euroAmount > Limits::WITHDRAW_LIMIT)
                                                    echo Helper::roundUp(($dates[$value[1]]["amount"] + $euroAmount - Limits::WITHDRAW_LIMIT) * Fees::PRIV_WITHDRAW);
                                                else
                                                    echo Helper::roundUp(0);
                                            }

                                            //update last withdraw info
                                            $dates[$value[1]]["time"] += 1;
                                            $dates[$value[1]]["amount"] += $euroAmount;
                                            $dates[$value[1]]["week_begin"] = date("Y-m-d", strtotime('monday this week', strtotime($value[0])));
                                            $dates[$value[1]]["week_end"] = date("Y-m-d", strtotime('sunday this week', strtotime($value[0])));
                                        } else {
                                            //check if more than withdraw limit
                                            if ($value[4] > $euroLimitByCurrency)
                                                echo Helper::roundUp(($value[4] - $euroLimitByCurrency) * Fees::PRIV_WITHDRAW);
                                            else
                                                echo Helper::roundUp(0);

                                            //update last withdraw info
                                            $dates[$value[1]]["time"] = 1;
                                            $dates[$value[1]]["amount"] = $euroAmount;
                                            $dates[$value[1]]["week_begin"] = date("Y-m-d", strtotime('monday this week', strtotime($value[0])));
                                            $dates[$value[1]]["week_end"] = date("Y-m-d", strtotime('sunday this week', strtotime($value[0])));
                                        }
                                    }
                                    break;
                            }
                            break;
                    }
                    echo "<p/>";
                }
            } else
                echo "File could not open.";
        } else
            echo "You need to choose a .csv file.";
    }
}
