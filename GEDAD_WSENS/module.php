<?php

declare(strict_types=1);
	class GEDAD_WSENS extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterVariableFloat ("Temperature", "Temperature",  "~Temperature", 10) ;
			$this->RegisterVariableInteger ("Humidity", "Humidity", "~Humidity" , 20) ;
			$this->RegisterVariableInteger ("AirQualityIndex", "Air Quality Index", "", 30) ;
			$this->RegisterVariableInteger ("CO2", "CO2", "~Occurrence.CO2", 40) ;
			$this->RegisterVariableFloat ("DuePoint", "DuePoint", "~Temperature", 50) ;
			$this->RegisterVariableInteger ("WhiteLightIntensity", "WhiteLightIntensity", "~Illumination", 60) ;
			
			$this->RegisterPropertyInteger("UpdateInterval", 60);
			$this->RegisterPropertyString("IPAddress", "192.168.178.1");

			$this->RegisterTimer("UpdateSensorData", ($this->ReadPropertyInteger("UpdateInterval"))*1000, 'WSENS_UpdateResult(' . $this->InstanceID . ');');
		}


		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			$this->SetTimerInterval("UpdateSensorData", ($this->ReadPropertyInteger("UpdateInterval"))*1000);

			$this->UpdateResult();
		}
	
		public function UpdateResult()
		{
			$ip_adress = $this->ReadPropertyString("IPAddress");

			$_url = "http://" . $ip_adress . "/JSON";
			
    		$data = @file_get_contents($_url);
			if ($data !== false)
			{
				$data = json_decode($data, true);
			
				$temp = floatval($data["Temperatur"]);
				$this->SetValue("Temperature", $temp);
				$humidity = intval($data["Luftfeuchtigkeit"]);
				$this->SetValue("Humidity", $humidity);

				$this->SetValue("AirQualityIndex", intval($data["Luftqualitaet-Index"]));

				$this->SetValue("CO2", intval($data["CO2"]));

				//$this->SetValue("DuePoint", 'WSENS_CalculateDuePoint(' . $this->InstanceID . ', $humidity, $temp );');
				$this->SetValue("DuePoint", $this->CalculateDuePoint( $humidity, $temp ));


				$this->SetValue("WhiteLightIntensity", intval($data["Intensitaet-Weiss"]));

				$this->SetStatus(102);
			}
			else
			{
				$this->SetStatus(200);
				$this->SetTimerInterval("UpdateSensorData", 0);
			}
			
		}

		private function CalculateDuePoint(int $humidity, float $temperature)
		{
			$kT = $temperature ;
			$kR = $humidity ;
			
			$a =  7.5;
			$b =  237.3;
			
			$SDD = 6.1078 * pow(10.0, ($a*$kT)/($b+$kT));
			
			$DD = $kR/100.0 * $SDD;
			$v = log10($DD/6.1078);
			
			$kTD = $b*$v/($a-$v);

			return $kTD;
		}


	
	}