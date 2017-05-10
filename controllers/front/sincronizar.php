<?php
class ProductostbSincronizarModuleFrontController extends ModuleFrontController
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
        $prod = json_decode(Tools::getValue('producto'));
        $nbr = Tools::getValue("nbr");
        $cont = Tools::getValue("cont");
        $ret = array("msg" => "");
        // print_r($prod);

        if(intval($prod->id) > 0){
            $producto = new Product($prod->id);
            $actualizar = true;
        }
        else{
            $producto = new Product();
            $actualizar = false;
        }

        $idiomas = $this->module->getIdiomas();
        
        foreach ($idiomas as $id_idioma){
            $producto->name[$id_idioma] = $prod->nombre;
        }

        /*categoria*/
        if(!is_array($prod->categoria) && $prod->categoria > 0){
            $producto->deleteCategories();
            $cats = array();
            $cats[] = $prod->categoria;
            $producto->addToCategories($cats);
            $producto->id_category_default = $prod->categoria;
        }

        $producto->save();

        /*Elimino las tallas (combinaciones) existentes*/
        $combinaciones = $producto->getAttributeCombinations(1);
        foreach ($combinaciones as $c){
            $producto->deleteAttributeCombination($c["id_product_attribute"]);
        }

        /*Asigno las nuevas tallas (combinaciones)*/
        foreach ($prod->tallas as $talla){
            $id_product_attribute = $producto->addProductAttribute(0, $talla->PESO, 0, 0, 0, array(), $producto->name[1] . "-" . $talla->NUMERO_TALLA, null, $talla->CODIGO_BARRAS, 0);
            
            $id_att = $talla->id_atributo;

            $sql = "insert into "._DB_PREFIX_."product_attribute_combination (id_attribute, id_product_attribute) values ($id_att, $id_product_attribute)";
            Db::getInstance()->Execute($sql);
        }

        /*características*/
        $sql = "delete from "._DB_PREFIX_."product_feature_value_pos where id_product = " . $prod->id;
        Db::getInstance()->Execute($sql);
        $sql = "delete from "._DB_PREFIX_."feature_product where id_product = " . $prod->id;
        Db::getInstance()->Execute($sql);

        $values = "";
        foreach($prod->caracteristicas as $car){
            $id_feature = $car->feature;
            $id_product = $prod->id;
            $id_feature_value = $car->value;
            $values .= "($id_feature, $id_product, $id_feature_value), ";
        }
        $values = rtrim($values, ", ");
        $sql = "insert into "._DB_PREFIX_."feature_product (id_feature, id_product, id_feature_value) values $values";
        Db::getInstance()->Execute($sql);

        if($actualizar){
            $ret["msg"] = "$cont de $nbr " . $prod->nombre . " actualizado";
            $this->module->log("producto " . $prod->nombre . " actualizado");
        }
        else{
            $ret["msg"] = "$cont de $nbr " . $prod->nombre . " creado";
            $this->module->log("producto " . $prod->nombre . " creado");
        }
        echo json_encode($ret);
    }
}