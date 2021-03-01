<?php

namespace App\Http\Controllers\API;

use App\CovidExposure;
use App\Exposure;
use App\HealthCareWorker;
use App\PartnerUser;
use App\Http\Resources\GenericCollection;
use App\Immunization;
use App\Jobs\SendSMS;
use App\NewExposure;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExposureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function exposures()
    {
        return new GenericCollection(NewExposure::where('user_id', \auth()->user()->id)->orderBy('id','desc')->paginate(100));

    }

    public function all_exposures()
    {
        return new GenericCollection(NewExposure::orderBy('id','desc')->paginate(100));

    }

    public function facility_exposures($id)
    {
        $hcws = HealthCareWorker::where('facility_id',$id)->pluck('user_id');

        Log::info("HCWs:". $hcws);

        return new GenericCollection(NewExposure::whereIn('user_id',$hcws)->orderBy('id','desc')->paginate(100));

    }

    public function partner_exposures($id)
    {
        $hcws = PartnerUser::where('partner_id',$id)->pluck('user_id');

        Log::info("HCWs:". $hcws);

        return new GenericCollection(NewExposure::whereIn('user_id',$hcws)->orderBy('id','desc')->paginate(100));

    }

    public function new_exposure(Request $request)
    {
        $request->validate([
            'exposure_date' => 'required',
            'exposure_location' => 'required',
            'exposure_type' => 'required',
            'patient_hiv_status' => 'required|in:POSITIVE,NEGATIVE,UNKNOWN',
            'patient_hbv_status' => 'required|in:POSITIVE,NEGATIVE,UNKNOWN',
            'previous_exposures' => 'required',
        ],[
//            'device_id.required' => 'Please select the device in use during exposure'
        ]);


        $exposure = new NewExposure();
        $exposure->user_id = \auth()->user()->id;
        $exposure->exposure_date = $request->exposure_date;
        $exposure->pep_date = $request->pep_date;
        $exposure->exposure_location = $request->exposure_location;
        $exposure->exposure_type = $request->exposure_type;
        $exposure->device_used = $request->device_used;
        $exposure->result_of	 = $request->result_of	;
        $exposure->device_purpose = $request->device_purpose;
        $exposure->exposure_when = $request->exposure_when;
        $exposure->exposure_description = $request->exposure_description;
        $exposure->patient_hiv_status = $request->patient_hiv_status;
        $exposure->patient_hbv_status = $request->patient_hbv_status;
        $exposure->previous_exposures = $request->previous_exposures;
        $exposure->previous_pep_initiated = $request->previous_pep_initiated;
        $exposure->saveOrFail();

        send_sms(auth()->user()->msisdn,  "Hello ".auth()->user()->first_name.", the exposure is regrettable. Kindly prevent infection by visiting your PEP care provider immediately. After 72 hours’ prevention is not effective.");

        SendSMS::dispatch(auth()->user(),"Hello ".auth()->user()->first_name.", it is 24 hours since you were exposed. Have you visited your PEP care provider? YES/NO. MOH")->delay(now()->addHours(24));

        return response()->json([
            'success' => true,
            'message' => 'Exposure has been reported successfully '
        ], 201);


    }

    public function new_covid_exposure(Request $request)
    {
        $request->validate([
            'id_no' => 'required',
            'date_of_contact' => 'required',
            'transmission_mode' => 'required',
            'contact_with' => 'required',
            'direct_covid_environment_contact' => 'required',
            'ppe_worn' => 'required',
            'ipc_training' => 'required',
            'covid_specific_training' => 'required',
            'risk_assessment_performed' => 'required',
            'pcr_test_done' => 'required',
            'exposure_management' => 'required',
            'isolation_start_date' => 'required',
            'county_id' => 'required',
            'subcounty_id' => 'required',
            'ward_id' => 'required',
        ],[
//            'device_id.required' => 'Please select the device in use during exposure'
        ]);


        DB::transaction(function() use ($request) {

            $cExposure = new CovidExposure();
            $cExposure->user_id = auth()->user()->id;
            $cExposure->id_no = $request->id_no;
            $cExposure->date_of_contact = $request->date_of_contact;
            $cExposure->transmission_mode = $request->transmission_mode;
            $cExposure->facility_of_exposure_id = $request->facility_of_exposure_id;
            $cExposure->procedure_perfomed = $request->procedure_perfomed;
            $cExposure->contact_with = $request->contact_with;
            $cExposure->direct_covid_environment_contact = $request->direct_covid_environment_contact;
            $cExposure->ppe_worn = $request->ppe_worn;
            $cExposure->ppes = $request->ppes;
            $cExposure->ipc_training = $request->ipc_training;
            $cExposure->ipc_training_period = $request->ipc_training_period;
            $cExposure->covid_specific_training = $request->covid_specific_training;
            $cExposure->covid_training_period = $request->covid_training_period;
            $cExposure->symptoms = $request->symptoms;
            $cExposure->risk_assessment_performed = $request->risk_assessment_performed;
            $cExposure->risk_assessment_outcome = $request->risk_assessment_outcome;
            $cExposure->risk_assessment_recommendation = $request->risk_assessment_recommendation;
            $cExposure->risk_assessment_decision_date = $request->risk_assessment_decision_date;
            $cExposure->pcr_test_done = $request->pcr_test_done;
            $cExposure->pcr_test_results = $request->pcr_test_results;
            $cExposure->exposure_management = $request->exposure_management;
            $cExposure->isolation_start_date = $request->isolation_start_date;
            $cExposure->saveOrFail();

            $ENDPOINT = "http://localhost:3000/api/register";
//            $ENDPOINT = "https://ears-covid.mhealthkenya.co.ke/api/register";

            $data=array();
            $data['first_name'] = auth()->user()->first_name;
            $data['last_name'] = auth()->user()->surname;
            $data['sex'] = auth()->user()->gender;
            $data['dob'] = optional(auth()->user()->hcw)->dob;
            $data['passport_number'] =$request->id_no;
            $data['phone_number'] = "+".auth()->user()->msisdn;
            $data['email_address'] = auth()->user()->email;
            $data['nationality'] = "KENYA";
            $data['origin_country'] = "KENYA";
            $data['place_of_diagnosis'] = "C4C";
            $data['date_of_contact'] = $request->date_of_contact;
            $data['county_id'] = $request->county_id;
            $data['subcounty_id'] = $request->subcounty_id;
            $data['ward_id'] = $request->ward_id;
            $body = http_build_query($data);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $ENDPOINT); // point to endpoint
//        curl_setopt($ch,CURLOPT_HTTPHEADER,array($HEADER));

            curl_setopt($ch, CURLOPT_VERBOSE, true);
            // curl_setopt($ch, CURLOPT_STDERR, $fp);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);  //data
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);// request time out
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0'); // no ssl verifictaion
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');


            $result=curl_exec($ch);
            curl_close($ch);

            Log::info("Sending data===>");
            //Log::info(json_decode($data, true));
            Log::info("Response from jitenge===>");

            Log::info(json_decode($result, true));


            send_sms(auth()->user()->msisdn,  "Dear ".auth()->user()->first_name.", Welcome to COVID-19 Exposure follow-up, it’s regrettable, Please note that you will be required to report on your symptoms daily for the next 14 days. MOH");
            SendSMS::dispatch(auth()->user(),"Dear ".auth()->user()->first_name.", thank you for observing your IPC guidelines. Have you had a COVID-19 PCR test? YES or NO. MOH")->delay(now()->addDays(8));
            SendSMS::dispatch(auth()->user(),"Dear ".auth()->user()->first_name.", thank you for observing the IPC guidelines, you have now completed the 14 days quarantine period. Have you resumed work? MOH")->delay(now()->addDays(15));

        });


        return response()->json([
            'success' => true,
            'message' => 'Covid-19 Exposure has been reported successfully '
        ], 201);


    }

    public function new_ussd_covid_exposure(Request $request)
    {
        $request->validate([
            'id_no' => 'required',
            'date_of_contact' => 'required',
            'transmission_mode' => 'required',
            'contact_with' => 'required',
            'direct_covid_environment_contact' => 'required',
            'ppe_worn' => 'required',
            'ipc_training' => 'required',
            'covid_specific_training' => 'required',
            'risk_assessment_performed' => 'required',
            'pcr_test_done' => 'required',
            'exposure_management' => 'required',
            'isolation_start_date' => 'required',
            
        ],[
//            'device_id.required' => 'Please select the device in use during exposure'
        ]);


        DB::transaction(function() use ($request) {

            $cExposure = new CovidExposure();
            $cExposure->user_id = auth()->user()->id;
            $cExposure->id_no = $request->id_no;
            $cExposure->date_of_contact = $request->date_of_contact;
            $cExposure->transmission_mode = $request->transmission_mode;
            $cExposure->facility_of_exposure_id = $request->facility_of_exposure_id;
            $cExposure->procedure_perfomed = $request->procedure_perfomed;
            $cExposure->contact_with = $request->contact_with;
            $cExposure->direct_covid_environment_contact = $request->direct_covid_environment_contact;
            $cExposure->ppe_worn = $request->ppe_worn;
            $cExposure->ppes = $request->ppes;
            $cExposure->ipc_training = $request->ipc_training;
            $cExposure->ipc_training_period = $request->ipc_training_period;
            $cExposure->covid_specific_training = $request->covid_specific_training;
            $cExposure->covid_training_period = $request->covid_training_period;
            $cExposure->symptoms = $request->symptoms;
            $cExposure->risk_assessment_performed = $request->risk_assessment_performed;
            $cExposure->risk_assessment_outcome = $request->risk_assessment_outcome;
            $cExposure->risk_assessment_recommendation = $request->risk_assessment_recommendation;
            $cExposure->risk_assessment_decision_date = $request->risk_assessment_decision_date;
            $cExposure->pcr_test_done = $request->pcr_test_done;
            $cExposure->pcr_test_results = $request->pcr_test_results;
            $cExposure->exposure_management = $request->exposure_management;
            $cExposure->isolation_start_date = $request->isolation_start_date;
            $cExposure->saveOrFail();

            $ENDPOINT = "http://localhost:3000/api/register";
//            $ENDPOINT = "https://ears-covid.mhealthkenya.co.ke/api/register";

            $data=array();
            $data['first_name'] = auth()->user()->first_name;
            $data['last_name'] = auth()->user()->surname;
            $data['sex'] = auth()->user()->gender;
            $data['dob'] = optional(auth()->user()->hcw)->dob;
            $data['passport_number'] =$request->id_no;
            $data['phone_number'] = "+".auth()->user()->msisdn;
            $data['email_address'] = auth()->user()->email;
            $data['nationality'] = "KENYA";
            $data['origin_country'] = "KENYA";
            $data['place_of_diagnosis'] = $request->place_of_diagnosis;
            $data['date_of_contact'] = $request->date_of_contact;
            $data['county_id'] = 2620;
            $data['subcounty_id'] = 2620;
            $data['ward_id'] = 2620;
            $body = http_build_query($data);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $ENDPOINT); // point to endpoint
//        curl_setopt($ch,CURLOPT_HTTPHEADER,array($HEADER));

            curl_setopt($ch, CURLOPT_VERBOSE, true);
            // curl_setopt($ch, CURLOPT_STDERR, $fp);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);  //data
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);// request time out
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0'); // no ssl verifictaion
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');


            $result=curl_exec($ch);
            curl_close($ch);

            Log::info("Sending data===>");
            //Log::info(json_decode($data, true));
            Log::info("Response from jitenge===>");

            Log::info(json_decode($result, true));

            send_sms(auth()->user()->msisdn,  "Hello ".auth()->user()->first_name.", the exposure is regrettable. Kindly self isolate yourself and follow Jitenge guidelines to report your daily symptoms. Download the Jitenge app from playstore.");


        });


        return response()->json([
            'success' => true,
            'message' => 'Covid-19 Exposure has been reported successfully '
        ], 201);


    }


    public function covid_exposures()
    {
        return new GenericCollection(CovidExposure::orderBy('id','desc')->paginate(20));

    }

    public function my_covid_exposures()
    {
        return new GenericCollection(CovidExposure::where('user_id', \auth()->user()->id)->orderBy('id','desc')->paginate(20));

    }

    public function facility_covid_exposures($id)
    {
        $hcws = HealthCareWorker::where('facility_id',$id)->pluck('user_id');

        Log::info("HCWs:". $hcws);

        return new GenericCollection(CovidExposure::whereIn('user_id',$hcws)->orderBy('id','desc')->paginate(20));

    }

    public function partner_covid_exposures($id)
    {
        $hcws = PartnerUser::where('partner_id',$id)->pluck('user_id')->first();

        Log::info("HCWs:". $hcws);

        return new GenericCollection(CovidExposure::whereIn('user_id',$hcws)->orderBy('id','desc')->paginate(20));

    }
}
