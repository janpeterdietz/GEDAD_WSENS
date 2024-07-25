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

			$this->RegisterVariableFloat ("MoldRisk", "MoldRisk", "~Temperature", 60) ;
			$this->RegisterVariableBoolean('MoldAlert', "MoldAlert", '~Alert', 70);
			
			$this->RegisterVariableInteger ("WhiteLightIntensity", "WhiteLightIntensity", "~Illumination", 90) ;
			
			$this->RegisterPropertyInteger("UpdateInterval", 60);
			$this->RegisterPropertyString("IPAddress", "192.168.178.1");

			$this->RegisterTimer("UpdateSensorData", ($this->ReadPropertyInteger("UpdateInterval"))*1000, 'WSENS_UpdateResult(' . $this->InstanceID . ');');
		}


		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			$this->SetSummary($this->ReadPropertyString("IPAddress"));
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
				$this->SetValue("MoldRisk", $this->CalculateMoldRisk( $humidity, $temp ));
				

				$this->SetValue("WhiteLightIntensity", intval($data["Intensitaet-Weiss"]));

				$this->SetStatus(102);
			}
			else
			{
				$this->SetStatus(200);
				//$this->SetTimerInterval("UpdateSensorData", 0);
			}
			
		}

		private function CalculateDuePoint(int $humidity, float $temperature) : float
		{
			//Source https://www.wetterochs.de/wetter/feuchte.html
	
			/*Bezeichnungen:
				r = relative Luftfeuchte
				T = Temperatur in °C
				TK = Temperatur in Kelvin (TK = T + 273.15)
				TD = Taupunkttemperatur in °C
				DD = Dampfdruck in hPa
				SDD = Sättigungsdampfdruck in hPa
	
				Parameter:
				a = 7.5, b = 237.3 für T >= 0
				a = 7.6, b = 240.7 für T < 0 über Wasser (Taupunkt)
				a = 9.5, b = 265.5 für T < 0 über Eis (Frostpunkt)
	
				R* = 8314.3 J/(kmol*K) (universelle Gaskonstante)
				mw = 18.016 kg/kmol (Molekulargewicht des Wasserdampfes)
				AF = absolute Feuchte in g Wasserdampf pro m3 Luft
	
				Formeln:
				SDD(T) = 6.1078 * 10^((a*T)/(b+T))
				DD(r,T) = r/100 * SDD(T)
				r(T,TD) = 100 * SDD(TD) / SDD(T)
				TD(r,T) = b*v/(a-v) mit v(r,T) = log10(DD(r,T)/6.1078)
				AF(r,TK) = 10^5 * mw/R* * DD(r,T)/TK; AF(TD,TK) = 10^5 * mw/R* * SDD(TD)/TK
				*/
	
			if ($temperature >= 0) {
				$a = 7.5;
				$b = 237.3;
			} else {
				$a = 7.6;
				$b = 240.7;
			}
	
			$SDD = function ($temperature) use ($a, $b)
			{
				$this->SendDebug('Sättitigungsdampfdruck', 6.1078 * pow(10, (($a * $temperature) / ($b + $temperature))) . ' hPa', 0);
				return 6.1078 * pow(10, (($a * $temperature) / ($b + $temperature)));
			};
	
			$DD = function ($humidity, $temperature) use ($SDD)
			{
				$this->SendDebug('Dampfdruck', $humidity / 100 * $SDD($temperature) . ' hPa', 0);
				return $humidity / 100 * $SDD($temperature);
			};
	
			$v = function ($humidity, $temperature) use ($DD)
			{
				return log10($DD($humidity, $temperature) / 6.1078);
			};
	
			$dewPoint = $b * $v($humidity, $temperature) / ($a - $v($humidity, $temperature));
	
			return $dewPoint;
		}

		private function CalculateMoldRisk(int $humidity, float $temperature): float
		{
			//DewPoint + ~3.3°C
			//Source https://sicherheitsingenieur.nrw/rechner/taupunkt-berechnen-schimmelgefahr/
	
			$dewPoint = $this->CalculateDuePoint($humidity, $temperature);
			$moldPoint = $dewPoint + 3.3;
	
			$this->SetAlert($moldPoint, $temperature);
	
			return $moldPoint;
		}
	
		private function SetAlert($moldPoint, $temperature)
		{
			//Set Alert true if it is false and moldpoint higher equal than temperature
			//Set Alert false if it is true and temperature is higher than moldpoint +1°C
	
			$alert = $this->GetValue('MoldAlert');
	
			$this->SendDebug('AlertValue', $alert, 0);
			$this->SendDebug('Moldpoint', $moldPoint, 0);
			$this->SendDebug('Temperature', $temperature, 0);
	
			if (!$alert && ($moldPoint >= $temperature)) {
				$this->SetValue('MoldAlert', true);
			} elseif ($alert && (($moldPoint + 1) < $temperature)) {
				$this->SetValue('MoldAlert', false);
			}
		}
	
	}