<?php 

session_start();

function getUserIP()
{
    $client  = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote  = $_SERVER['REMOTE_ADDR'];

    if(filter_var($client, FILTER_VALIDATE_IP))
    {
        $ip = $client;
    }
    elseif(filter_var($forward, FILTER_VALIDATE_IP))
    {
        $ip = $forward;
    }
    else
    {
        $ip = $remote;
    }

    return $ip;
}


$user_ip = getUserIP();
if(strlen($user_ip) > 5){
    $geo = json_decode(file_get_contents('http://www.geoplugin.net/json.gp?ip='.$user_ip));
    if($geo->geoplugin_status == 200){
        $_SESSION['evento'] = $geo->geoplugin_countryCode;
    }
} else {
    $_SESSION['evento'] = 'CL';
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <base href="./">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Practia te pinta | Practia</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <!-- inject:css -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.99.0/css/materialize.min.css">
    <link href="css/less-space.min.css" rel="stylesheet" type="text/css">
    <link type="text/css" rel="stylesheet" href="css/practia.css" media="screen,projection" />
    <!-- endinject -->
</head>

<?php

include 'db.php';
include 'codes.php';

$uploadMessage = '';
$uploadOk = 1;
$showMessage = false;
$show_link_image = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showMessage = true;

    if(isset($_POST['uniqid']) AND isset($_SESSION['uniqid']) AND $_POST['uniqid'] == $_SESSION['uniqid']){
        // can't submit again
        $uploadMessage = 'Completa el formulario nuevamente.';
        $uploadOk = 0;
        $codigo = '';
        //$show_link_image = isset($_SESSION['show_link']);

        $usuario = $_POST["usuario"];
        $correo = $_POST["correo"];
        $empresa = $_POST["empresa"];
        $cargo = $_POST["cargo"];
    }
    else{
        $_SESSION['uniqid'] = $_POST['uniqid'];

        $usuario = $_POST["usuario"];
        $correo = $_POST["correo"];
        $empresa = $_POST["empresa"];
        $cargo = $_POST["cargo"];
        $codigo = strtolower($_POST["codigo"]);
        $estilo = $_POST["estilo"];
        if(isset($_SESSION['evento']) && strlen($_SESSION['evento']) > 0){
            $evento = $_SESSION["evento"];
        } else {
            $evento = $_POST["evento"];
        }

        #validar codigo
        $code_valid = json_decode(valid_code($codigo), true);
        if(!$code_valid['valid']){
            $uploadMessage = $code_valid['message'];
            $uploadOk = 0;
            $codigo = '';
            //$show_link_image = true;
            $_SESSION['show_link'] = true;
        } else {
            $codigo = $code_valid['code'];
            $target_dir = "uploads/";
            $image_name = basename($_FILES["imagen"]["name"]);
            $image_name_clean = str_replace(' ', '', $image_name);
            $target_file = $target_dir . $image_name_clean;
            $imageFileType = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
                $uploadMessage = "Sube una imagen JPG, JPEG o PNG.";
                $uploadOk = 0;
            }
            // if everything is ok, try to upload file
            else {
                if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $target_file)) {
                    $uploadMessage = "Se ha enviado tu imagen \"". $image_name . "\"";    

                    //guardar imagen en BD
                    $image = addslashes(file_get_contents($target_file)); //SQL Injection defence!
                    $name = pathinfo($image_name_clean, PATHINFO_FILENAME);
                    $ext = '.jpg';// '.' . $imageFileType;
                    $sql = "INSERT INTO `image` (`usuario`, `ip`, `correo`, `empresa`, `cargo`, `estilo`, `name`, `ext`, `imagen`, `status`)
                                        VALUES ('$usuario', '$evento', '$correo', '$empresa', '$cargo', '$estilo', '$name', '$ext', '$image', 'A_PROCESAR')";
                    
                    $conn = get_conn();
                    if ($conn->query($sql) === TRUE) {
                        $last_id = $conn->insert_id;
                        if(!empty($codigo)){
                            $sql = "UPDATE `code` SET `status` = 1, `image_id` = $last_id WHERE `key` = '$codigo'";
                            $conn->query($sql);
                        }
                        $uploadMessage = "Se ha enviado tu imagen \"". $image_name . "\""; 
                        //$show_link_image = true;
                        $_SESSION['show_link'] = true;
                        $codigo = '';
                    } else {
                        $uploadMessage = "No se pudo guardar tu imagen, intenta con una más pequeña (máx. 3MB)";
                        $uploadOk = 0;
                    }
                    $conn->close();
                } else {
                    $uploadMessage = "No se pudo guardar tu imagen";
                    $uploadOk = 0;
                }
            }   
        }
    }
}

?>

<body>
    <header>
        <div class="navbar-fixed">
            <nav>
                <div class="nav-wrapper z-depth-1">
                    <a href="./" class="brand-logo left"><img src="images/logo.png" alt="Practia" class="responsive-img"></a>
                    <ul id="nav-mobile" class="right">
                        <li><a href="./demos.php">Demos</a></li>
                    </ul>
                </div>
                <div class="progress-bar">
                    <div class="progress z-depth-1 hide">
                        <div class="indeterminate"></div>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    <main>
        <div class="row xs-pl-20 xs-pr-20<?php if(!$showMessage){ echo ' hide'; } ?>">
            <div class="col s12">
                <h5<?php if($uploadOk){ echo ''; } else { echo ' class="error"'; } ?>><?php echo $uploadMessage; ?></h5>
            </div>
        </div>
        <?php if($show_link_image) { ?>
        <div class="row center-align"> <a href="./imagenes.php?correo=<?php echo $_POST['correo']; ?>" target="_blank" class="waves-effect waves-light btn"><i class="material-icons left">photo_library</i>Ver mis imagenes</a> </div>
        <?php } ?>
        <div class="row xs-pl-20 xs-pr-20">
            <div class="col s12">
                <h5>¿Qué son las REDES NEURONALES?</h5>
                <p>Las <b>Redes Neuronales Artificiales</b> imitan el funcionamiento del cerebro humano en un computador. Integrando
                    sistemas simples, llamados perceptrones, es posible construir aplicaciones complejas tales como comprender
                    el lenguaje; identificar los objetos en imágenes o videos y clasificar documentos.</p>
                <p><b>En Practia entrenamos nuestra red neuronal, para que pinte tu imagen aplicando el estilo artístico que elijas.</b></p>
            </div>
        </div>
        <div class="row">
            <form id="form-file" class="col s12" action="./" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="input-field col s12 m6"> <input name="usuario" id="usuario" type="text" class="validate" required> <label for="usuario">* Nombre</label></div>
                    <div class="input-field col s12 m6"> <input name="correo" id="correo" type="email" class="validate" required> <label for="correo" data-error="Correo no válido">* Correo</label>                        </div>
                </div>
                <div class="row">
                    <div class="input-field col s12 m6"> <input name="empresa" id="empresa" type="text" class="validate" > <label for="empresa">* Empresa</label></div>
                    <div class="input-field col s12 m6"> <input name="cargo" id="cargo" type="text" class="validate" > <label for="cargo">* Cargo</label>                        </div>
                </div>
                <div class="row valign-wrapper">
                    <div class="col hide-on-med-and-up s1">
                        <a onclick="Materialize.toast($toastContent, 4000)">
                        <i class="material-icons prefix">info_outline</i></a>
                    </div>
                    <div class="col hide-on-small-only m1">
                        <a class="tooltipped" data-position="right" data-delay="50" data-tooltip="Consigue e ingresa un código para imprimir tu foto con el estilo que quieras"><i class="material-icons prefix">info_outline</i></a>
                    </div>
                    <div class="input-field col s10 m11">                        
                        <input name="codigo" id="codigo" type="text" class="validate">
                        <label for="codigo">Código para imprimir</label>
                    </div>
                </div>
                <?php if(!isset($_SESSION['evento']) | strlen($_SESSION['evento']) < 1) { ?>
                <div class="row hidden">                
                    <div class="input-field col s12">
                        <select name="evento" >
                            <option value="" disabled selected>Selecciona un evento</option>
                            <option value="AR">Argentina</option>
                            <option value="CL">Chile</option>
                            <option value="PE">Perú</option>
                        </select> <label>* Evento</label>
                    </div>
                </div>
                <?php } ?>
                <div class="row">
                    <div class="input-field col s12 m4 push-m8"> <select name="estilo" class="icons" required>
                            <option value="" disabled selected>Selecciona un estilo</option>
                            <option value="mosaic" data-icon="images/styles/mosaic.jpg" class="left circle">Mosaico</option>
                            <option value="candy" data-icon="images/styles/candy.jpg" class="left circle">Candy</option>
                            <option value="udnie" data-icon="images/styles/udnie.jpg" class="left circle">Udnie</option>
                            <option value="starry-night" data-icon="images/styles/starry-night.jpg" class="left circle">Starry Night</option>
                            <option value="vg_portrait" data-icon="images/styles/van-gogh.jpg" class="left circle">van Gogh</option>
                        </select> <label>* Estilo</label> </div>
                    <div class="file-field input-field col s12 m8 pull-m4">
                        <div class="btn"><i class="material-icons left">photo</i><span>Imagen</span> <input name="imagen" type="file"
                                required> </div>
                        <div class="file-path-wrapper"> <input class="file-path" type="text"> </div>
                    </div>
                </div>
                <input type="hidden" name="uniqid" value="<?php echo uniqid();?>" />
                <div class="row center-align"> <button class="btn waves-effect waves-light btn-large" type="submit" name="action">Enviar<i class="material-icons right">send</i></button>                    </div>
            </form>
        </div>
    </main>
    <footer> </footer>
    <div class="overlay fixed hide"></div>
    <!-- inject:css -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/0.99.0/js/materialize.min.js"></script>
    <script type="text/javascript" src="js/practia.js"></script>
    <script>
        var $toastContent = $('<p class="center-align break-word">Consigue un código para imprimir tu foto con el estilo que quieras</p>');
        $(document).ready(function () {
        <?php
                if (isset($usuario)) {
                    echo "$('#usuario').val('" . $usuario ."');";
                }
                if (isset($correo)) {
                    echo "$('#correo').val('" . $correo ."');";
                }
                if (isset($empresa)) {
                    echo "$('#empresa').val('" . $empresa ."');";
                }
                if (isset($cargo)) {
                    echo "$('#cargo').val('" . $cargo ."');";
                }
                if (isset($codigo)) {
                    echo "$('#codigo').val('" . $codigo ."');";
                }
                echo 'Materialize.updateTextFields();';
            
        ?>
        $('select').material_select();

        // for HTML5 "required" attribute
        $("select[required]").css({ display: "block", height: 0, padding: 0, margin: "0 60px", width: 0, position: "relative", top: "-18px" });
        $(".file-field > .btn input[type=file]").css({ bottom: "18px" });
    });
    </script>
    <!-- endinject -->
</body>

</html>
