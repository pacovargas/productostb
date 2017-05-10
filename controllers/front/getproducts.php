<?php
class ProductostbGetProductsModuleFrontController extends ModuleFrontController
{
    public function __construct($response = array())
    {
        parent::__construct($response);
        $this->display_header = false;
        $this->display_header_javascript = false;
        $this->display_footer = false;
    }

    public function postProcess()
    {
        set_time_limit(0);
        $idiomas = $this->module->getIdiomas();
        $ret = array('success' => true);
        $conn_id = ftp_connect(Configuration::get('PRODUCTOSTB_SERVER', null));
        $login_result = ftp_login($conn_id, Configuration::get('PRODUCTOSTB_USER', null), Configuration::get('PRODUCTOSTB_PASSWORD', null));
        if ((!$conn_id) || (!$login_result)) {
            $ret['success'] = false;
            if(!$conn_id){
                $ret['error'] = "Fallo conectando a FTP";
            }
            else{
                $ret['error'] = "Fallo logueando FTP " . Configuration::get('PRODUCTOSTB_USER', null) . " - " . Configuration::get('PRODUCTOSTB_PASSWORD', null);   
            }
        }
        else{
            $remote_file = Configuration::get('PRODUCTOSTB_RUTA', null);
            $local_file = 'local.xml';
            if(!$xml = $this->ftp_get_string($conn_id, $remote_file)){
                $ret['success'] = false;
            }
            else{
                $productos = $this->xmlstring2array($xml);
                $ret["productos"] = array();
                // $productos_por_nombre = $this->getProductsByName();
                $atributos_por_nombre = $this->getAttributesByName();
                foreach ($productos["PRODUCTO"] as $key => $producto){
                    $ret["productos"][$key]["nombre"] = $producto["DESCRIPCION_PRODUCTO"];
                    $ret["productos"][$key]["tallas"] = $producto["TALLAS"]["TALLA"];
                    // $ret["productos"][$key]["id"] = intval($productos_por_nombre[$producto["DESCRIPCION_PRODUCTO"]]);
                    $ret["productos"][$key]["id"] = intval($producto["COD_PRODUCTO_PROCESIVA"]);
                    $ret["productos"][$key]["categoria"] = intval($producto["CODIGO_CATEGORIA"]);
                }

                /*TALLAS*/
                foreach ($ret["productos"] as $key => $prod){
                    foreach($ret["productos"][$key]["tallas"] as $index => $t){
                        if(isset($atributos_por_nombre[$t["NUMERO_TALLA"]]) && intval($atributos_por_nombre[$t["NUMERO_TALLA"]]) > 0){
                            $ret["productos"][$key]["tallas"][$index]["id_atributo"] = intval($atributos_por_nombre[$t["NUMERO_TALLA"]]);
                        }
                        else{
                            $new_at = new Attribute();
                            foreach ($idiomas as $id_idioma){
                                $new_at->name[$id_idioma] = $t["NUMERO_TALLA"];
                            }
                            $new_at->id_attribute_group = 1;
                            $new_at->position = Attribute::getHigherPosition(1) + 1;
                            $new_at->save();
                            $ret["productos"][$key]["tallas"][$index]["id_atributo"] = $new_at->id;
                            $atributos_por_nombre[$t["NUMERO_TALLA"]] = $new_at->id;
                            $this->module->log("Talla " . $t["NUMERO_TALLA"] . " creada");
                        }
                    }
                }

                $caracteristicas = $this->getCaracteristicas();
                // ppp($caracteristicas);
                
                /*BOLSAS Y CAJAS*/
                $id_caja = $caracteristicas["caja"];
                $valores = $this->getValores($id_caja);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    $tipo_caja = strtolower($p["TIPO_CAJA"]);
                    if($tipo_caja != "nada"){
                        if(isset($valores[$tipo_caja])){
                            $valor_tipo_caja = $valores[$tipo_caja];
                        }
                        else{
                            $valor_tipo_caja = $this->createFeatureValue($id_caja, strtolower($tipo_caja), $idiomas);
                            $valores[$tipo_caja] = $valor_tipo_caja;
                            $this->module->log("Caja $tipo_caja creada");
                        }
                        $ret["productos"][$key]["caracteristicas"][] = array(
                            "feature" => $id_caja,
                            "value" =>  $valor_tipo_caja
                        );
                    }
                }

                $id_bolsa = $caracteristicas["bolsa"];
                $valores = $this->getValores($id_bolsa);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    $tipo_bolsa = strtolower($p["TIPO_BOLSA"]);
                    if($tipo_bolsa != "nada"){
                        if(isset($valores[$tipo_bolsa])){
                            $valor_tipo_bolsa = $valores[$tipo_bolsa];
                        }
                        else{
                            $valor_tipo_bolsa = $this->createFeatureValue($id_bolsa, strtolower($tipo_bolsa), $idiomas);
                            $valores[$tipo_bolsa] = $valor_tipo_bolsa;
                            $this->module->log("Bolsa $tipo_bolsa creada");
                        }
                        $ret["productos"][$key]["caracteristicas"][] = array(
                            "feature" => $id_bolsa,
                            "value" =>  $valor_tipo_bolsa
                        );
                    }
                }

                /*EMBALAJES OPCIONALES*/
                $id_embalajes = $caracteristicas["embalajes opcionales"];
                $valores = $this->getValores($id_embalajes);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    if($p["EMBALAJE_BLISTER"] == "S"){
                        $ret["productos"][$key]["caracteristicas"][] = array(
                            "feature" => $id_embalajes,
                            "value" =>  $valores["blister"]
                        );
                    }
                    if($p["EMBALAJE_BOLSA_TB"] == "S"){
                        $ret["productos"][$key]["caracteristicas"][] = array(
                            "feature" => $id_embalajes,
                            "value" =>  $valores["bolsa contínua"]
                        );
                    }
                    if($p["EMBALAJE_CODIGO_BARRAS"] == "S"){
                        $ret["productos"][$key]["caracteristicas"][] = array(
                            "feature" => $id_embalajes,
                            "value" =>  $valores["código de barras"]
                        );
                    }
                }

                /*NORMATIVAS*/
                $id_normativa = $caracteristicas["normativa"];
                $valores = $this->getValores($id_normativa);
                $valores = $this->transformaNormativas($valores);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    foreach ($p as $index => $value){
                        if(strpos($index, "NORMATIVA") !== false && strpos($index, "VALORES") === false){
                            if($value == "S"){
                                $normativa = strtolower(substr($index, 10));
                                $ret["productos"][$key]["caracteristicas"][] = array(
                                    "feature" => $id_normativa,
                                    "value" =>  $valores[$normativa]
                                );
                            }
                        }
                    }
                }

                /*RIESGOS*/
                $id_riesgo = $caracteristicas["riesgos"];
                $valores = $this->getValores($id_riesgo);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    if(isset($p["RIESGOS"])){
                        $riesgos = array();
                        if(isset($p["RIESGOS"]["RIESGO"][0])){
                                foreach($p["RIESGOS"]["RIESGO"] as $r){
                                    $riesgos[]=strtolower($r["DESCRIPCION_RIESGO"]);
                                }
                        }
                        else{
                            $riesgos[]=strtolower($p["RIESGOS"]["RIESGO"]["DESCRIPCION_RIESGO"]);
                        }
                        
                        foreach($riesgos as $riesgo){
                            if(isset($valores[$riesgo])){
                                $valor_riesgo = $valores[$riesgo];
                            }
                            else{
                                $valor_riesgo = $this->createFeatureValue($id_riesgo, $riesgo, $idiomas);
                                $valores[$riesgo] = $valor_riesgo;
                                $this->module->log("Riesgo $riesgo creado");
                            }

                            $ret["productos"][$key]["caracteristicas"][] = array(
                                "feature" => $id_riesgo,
                                "value" =>  $valor_riesgo
                            );
                        }
                    }
                }


                /*USOS*/
                $id_uso = $caracteristicas["usos recomendados"];
                $valores = $this->getValores($id_uso);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    if(isset($p["USOS"])){
                        $usos = array();
                        if(isset($p["USOS"]["USO"][0])){
                                foreach($p["USOS"]["USO"] as $r){
                                    $usos[]=strtolower($r["DESCRIPCION_USO"]);
                                }
                        }
                        else{
                            $usos[]=strtolower($p["USOS"]["USO"]["DESCRIPCION_USO"]);
                        }
                        
                        foreach($usos as $uso){
                            if(isset($valores[$uso])){
                                $valor_uso = $valores[$uso];
                            }
                            else{
                                $valor_uso = $this->createFeatureValue($id_uso, $uso, $idiomas);
                                $valores[$uso] = $valor_uso;
                                $this->module->log("Uso $uso creado");
                            }

                            $ret["productos"][$key]["caracteristicas"][] = array(
                                "feature" => $id_uso,
                                "value" =>  $valor_uso
                            );
                        }
                    }
                }

                /*SECTORES*/
                $id_sector = $caracteristicas["sector"];
                $valores = $this->getValores($id_sector);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    if(isset($p["SECTORES"])){
                        $sectores = array();
                        if(isset($p["SECTORES"]["SECTOR"][0])){
                                foreach($p["SECTORES"]["SECTOR"] as $r){
                                    $sectores[]=strtolower($r["DESCRIPCION_SECTOR"]);
                                }
                        }
                        else{
                            $sectores[]=strtolower($p["SECTORES"]["SECTOR"]["DESCRIPCION_SECTOR"]);
                        }
                        
                        foreach($sectores as $sector){
                            if(isset($valores[$sector])){
                                $valor_sector = $valores[$sector];
                            }
                            else{
                                $valor_sector = $this->createFeatureValue($id_sector, $sector, $idiomas);
                                $valores[$sector] = $valor_sector;
                                $this->module->log("Sector $sector creado");
                            }

                            $ret["productos"][$key]["caracteristicas"][] = array(
                                "feature" => $id_sector,
                                "value" =>  $valor_sector
                            );
                        }
                    }
                }

                /*CARACTERÍSTICAS*/
                // ppp($caracteristicas);
                foreach ($productos["PRODUCTO"] as $key => $p){
                    foreach ($p as $index => $value){
                        if(strpos($index, "CARACTERISTICAS") !== false && strpos($index, "DESCRIPCION") === false){
                            if(!is_array($value)){
                                $carnombre = strtolower(str_replace("_", " ", substr($index, 16)));
                                if(isset($caracteristicas[$carnombre])){
                                    $id_car = $caracteristicas[$carnombre];
                                }
                                else{
                                    $id_car = $this->createFeature($carnombre, $idiomas);
                                    $this->module->log("Caracteristica $carnombre creada");
                                }
                                // ppp($carnombre . " - " . $value . " - " . $id_car);
                                $valores = $this->getValores($id_car);
                                if(isset($valores[strtolower($value)])){
                                    $id_valor = $valores[strtolower($value)];
                                }
                                else{
                                    $id_valor = $this->createFeatureValue($id_car, strtolower($value), $idiomas);
                                    $this->module->log("Valor $value creado para la caracterísitca $carnombre");
                                }
                                $ret["productos"][$key]["caracteristicas"][] = array(
                                    "feature" => $id_car,
                                    "value" =>  $id_valor
                                );
                            }
                        }
                    }
                }

            }
        }
        echo json_encode($ret);
        // ppp($ret);
    }


    private function ftp_get_string($ftp, $filename) {
        $temp = fopen('php://temp', 'r+');
        if (@ftp_fget($ftp, $temp, $filename, FTP_BINARY, 0)) {
            rewind($temp);
            return stream_get_contents($temp);
        }
        else {
            return false;
        } 
    }

    private function xmlstring2array($string)
    {
        $xml   = simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NOCDATA);
        $array = json_decode(json_encode($xml), TRUE);
        return $array;
    }

    private function getProductsByName(){
        $productos = Product::getProducts(1, 0, 0, 'id_product', 'ASC');
        $ret = array();
        foreach ($productos as $p) {
            $ret[$p["name"]] = $p["id_product"];
        }
        return $ret;
    }

    private function getAttributesByName(){
        $atributos = Attribute::getAttributes(1);
        $ret = array();
        foreach ($atributos as $a){
            $ret[$a["name"]] = $a["id_attribute"];
        }
        return $ret;
    }

    private function getCaracteristicas(){
        $caracteristicas = Feature::getFeatures(1);
        $ret = array();
        foreach ($caracteristicas as $c){
            $ret[strtolower($c["name"])] = $c["id_feature"];
        }
        return $ret;
    }

    private function getValores($id_feature){
        $valores = FeatureValue::getFeatureValuesWithLang(1, $id_feature);
        $ret = array();
        foreach ($valores as $v){
            $ret[strtolower($v["value"])] = $v["id_feature_value"];
        }
        return $ret;
    }

    private function transformaNormativas($valores){
        $ret = array();

        foreach ($valores as $normativa => $id){
            $arr_normativa = explode(":", $normativa);
            $nombre_normativa = $arr_normativa[0];

            if(isset($arr_normativa[1]) && strpos($arr_normativa[1], "-") !== false){
                $nombre_normativa2 = str_replace(" ", "", $arr_normativa[1]);
                $arr_normativa2 = explode("-", $nombre_normativa2);
                $nombre_normativa .= "-" . $arr_normativa2[1];
            }
            $nombre_normativa = str_replace(" ", "-", $nombre_normativa);
            $ret[$nombre_normativa] = $id;
        }

        return $ret;
    }

    private function createFeatureValue($id_padre, $valor, $idiomas){
        $fv = new FeatureValue();
        $fv->id_feature = $id_padre;
        foreach ($idiomas as $idioma){
            $fv->value[$idioma] = $valor;
        }
        $fv->save();
        return $fv->id;
    }

    private function createFeature($nombre, $idiomas){
        $f = new Feature();
        foreach($idiomas as $idioma){
            $f->name[$idioma] = $nombre;
        }
        $f->save();
        return $f->id;
    }
}