<?php
ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);
#!/usr/bin/php -q
include_once('/var/www/html/dia-a-dia/servidor/index.php');
include_once("/var/www/html/dia-a-dia/servidor/DbPDO.class.php");
include_once('/var/www/html/dia-a-dia/servidor/queriesPopulate.php');
include_once('/var/www/html/dia-a-dia/servidor/queries.php');

//DB
$db = new DbPDO('central');

if (file_get_contents("php://input")) {
    $data = json_decode(file_get_contents("php://input"), TRUE);
}
if ($data && $data['datai'] && $data['datai'] != '' && $data['uf']) {
    $datai_format = str_replace('/', '-', $data['datai']);
    $datai = date('Y-m-d', strtotime($datai_format));
    $dataf = date("Y-m-d", strtotime($datai . '+1 day'));
    $dataiMedia = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-' . '01' . '-01'));
    $datafInter = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-01'));
    $datafMedia = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-' . date('t', strtotime($datafInter))));
    //params
    $params = array('datai' => $datai, 'dataf' => $dataf, 'dataiMedia' => $dataiMedia, 'datafMedia' => $datafMedia);
    echo "Params comuns";
} else {
    /**
     * PARAMS FOR CRON JOB
     */
    echo "Params Crown Job \r\n";
    echo "Iniciando parâmetros de data \r\n";
    $datai = date('Y-m-d', strtotime('-1 day'));
    $dataCronJob = date('Y-m-d H:i');
    $dataf = date('Y-m-d', strtotime($datai . '+1 day'));
    $dataiMedia = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-' . '01' . '-01'));
    $datafInter = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-01'));
    $datafMedia = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-' . date('t', strtotime($datafInter))));

    $params = array(
        'datai' => $datai, 'dataf' => $dataf, 'dataiMedia' => $dataiMedia, 'datafMedia' => $datafMedia,
        'dataCronJob' => $dataCronJob
    );
    echo "Parâmetros iniciados... \n\n";
    var_dump($params);
    echo "\n";
}
$queryAghuCC = array(
    'query_CC' => $query_CC,
    'query_cirg_porte' => $query_cirg_porte,
    'query_cirg_porte_media' => $query_cirg_porte_media,
    'query_media_diaria' => $query_media_diaria,
    'query_CCAgendadas' => $query_CCAgendadas
);
$queryAghuApoioDiag = array(
    'query_ExamesRadiologia' => $query_ExamesRadiologia,
    'query_ExamesRadiologiaMedia' => $query_ExamesRadiologiaMedia
);
$queryAghuEnfermarias = array(
    'query_EnfermariasInternacoes' => $query_EnfermariasInternacoes,
    'query_Enfermarias' => $query_Enfermarias
);
$queryConsultasAmbulatoriais = array(
    'query_ConsultasAmbulatoriais' => $query_ConsultasAmbulatoriais,
    'query_ConsultasAmbulatoriaisMedia' => $query_ConsultasAmbulatoriaisMedia
);

function populateCC($queryAghuCC, $params)
{
    $resultAghu = executeCC($queryAghuCC, $params, false);
    $resultAghuAmb = executeCC($queryAghuCC, $params, true);
    $subItems =  array(
        'agendado' => 8, 'canceladas' => 9, 'eletivas' => 163, 'urgencia' => 10, 'amb' => 168,
        'pequeno' => 171, 'medio' => 170, 'grande' => 169,
    );
    $itemsMedia = array(
        'magendado' => 59, 'mcanceladas' => 60, 'meletivas' => 61, 'murgencia' => 62, 'mambu' => 63,
        'mpequeno' => 64, 'mmedio' => 65, 'mgrande' => 66, 'mrealizadas' => 14,
    );

    $arrayCirurgias = array();
    $cirurgias = array();
    $cirurgiasMedia = array();
    $cirurgiasPorPorte = array();
    $cirurgiasPorPorteMedia = array();
    $cirurgias['agendado'] = (int)$resultAghu['cirurgias_total_agendadas'][0]["Total"];

    //Divisão sendo realizada e código para reutilizar consulta
    $cirurgiasMedia['magendado'] = (int)$resultAghu['cirurgias_total_agendadas_media'][0]["Total"] / 365;

    $arrayCirurgias = formatArray($resultAghu, 'cirurgias');
    $arrayCirurgiasMedia = formatArray($resultAghu, 'cirurgia_media_diaria');
    $arrayCirurgiasPorte = formatArray($resultAghu, 'cirurgias_por_porte');
    $arrayCirurgiasPorteMedia = formatArray($resultAghu, 'cirurgias_por_porte_media');
    $arrayCirurgiasAmb = formatArray($resultAghuAmb, 'cirurgias');
    $arrayCirurgiasAmbMedia = formatArray($resultAghuAmb, 'cirurgia_media_diaria');
    if ($arrayCirurgiasAmb != null) {
        foreach ($arrayCirurgiasAmb as $key => $value) {
            if ($value['ind_realizada'] == "Realizadas") {
                $cirurgias['amb'] += $value['Num de Cirurgias no Dia'];
            }
        }
    }
    foreach ($arrayCirurgiasAmbMedia as $value) {
        if ($value['situacao'] == "AGND") {
            $cirurgiasMedia['mambu'] += $value['Media Diaria do Ano Anterior'];
        }
    }
    foreach ($arrayCirurgias as $key => $value) {
        if ($value['ind_realizada'] == "Realizadas") {
            if ($value["Natureza do Agendamento"] == 'ELE') {
                $cirurgias['eletivas'] = (int)$value["Num de Cirurgias no Dia"];
            } else if ($value["Natureza do Agendamento"] == 'URG') {
                $cirurgias['urgencia'] = (int)$value["Num de Cirurgias no Dia"];
            } else if ($value["Natureza do Agendamento"] == 'EMG') {
                $cirurgias['emergencia'] = (int)$value["Num de Cirurgias no Dia"];
            }
            // $cirurgias['totalRealizado'] += $value["Num de Cirurgias no Dia"];
        } else if ($value['situacao'] == 'CANC') {
            $cirurgias['canceladas'] += $value["Num de Cirurgias no Dia"];
        }
    }
    foreach ($arrayCirurgiasMedia as $key => $value) {
        if ($value['situacao'] == 'AGND') {
            if ($value['Natureza do Agendamento'] == 'ELE') {
                $cirurgiasMedia['meletivas'] = (int)round((float)$value['Media Diaria do Ano Anterior']);
            } else if ($value["Natureza do Agendamento"] == 'URG') {
                $cirurgiasMedia['murgencia'] = (int)round((float)$value['Media Diaria do Ano Anterior']);
            } else if ($value["Natureza do Agendamento"] == 'EMG') {
                $cirurgiasMedia['memergencia'] = (int)round((float)$value['Media Diaria do Ano Anterior']);
            }
            $cirurgiasMedia['mrealizadas'] += (int)round((float)$value['Media Diaria do Ano Anterior']);
        } else if ($value['situacao'] == 'CANC') {
            $cirurgiasMedia['mcanceladas'] += (int)round((float)$value['Media Diaria do Ano Anterior']);
        }
    }
    foreach ($arrayCirurgiasPorte as $key => $value) {
        if ($value['porte'] == '1. Pequeno') {
            $cirurgiasPorPorte['pequeno'] = (int)$value['Total por Porte'];
        } else if ($value['porte'] == '2. Medio') {
            $cirurgiasPorPorte['medio'] = (int)$value['Total por Porte'];
        } else if ($value['porte'] == '3. Grande') {
            $cirurgiasPorPorte['grande'] = (int)$value['Total por Porte'];
        }
    }
    foreach ($arrayCirurgiasPorteMedia as $value) {
        if ($value['porte'] == '1. Pequeno') {
            $cirurgiasPorPorteMedia['mpequeno'] = (int)$value['Total por Porte(arredondado)'];
        } else if ($value['porte'] == '2. Medio') {
            $cirurgiasPorPorteMedia['mmedio'] = (int)$value['Total por Porte(arredondado)'];
        } else if ($value['porte'] == '3. Grande') {
            $cirurgiasPorPorteMedia['mgrande'] = (int)$value['Total por Porte(arredondado)'];
        }
    }
    $cirurgias = array_merge($cirurgias, $cirurgiasPorPorte);
    $cirurgiasMedia = array_merge($cirurgiasMedia, $cirurgiasPorPorteMedia);
    if ($params) {
        executeDiario($cirurgias, $subItems, $params);
        executeMedia($cirurgiasMedia, $itemsMedia, $params);
    }
    return 0;
}
function  populateApoioDiag($queryApoioDiag, $params)
{
    $subItems = array(
        'tomografia' => 35, 'ultrasson' => 28, 'raiox' => 38, 'broncoscopia' => 32,
        'ecocardiograma' => 31, 'colonoscopia' => 30, 'endoscopia' => 29, 'laboratorio' => 43
    );
    $itemsMedia = array(
        'raiox' => 58, 'tomografia' => 37, 'laboratorio' => 8, 'ecocardiograma' => 30,
        'endoscopia' => 54, 'colonoscopia' => 33, 'broncoscopia' => 56, 'ultrasson' => 32
    );
    $resultAghu = executeApoioDiag($queryApoioDiag, $params);
    $arrayExames = formatArray($resultAghu, 'exames');
    $arrayExamesMedia = formatArray($resultAghu, 'examesMedia');
    $exames = array();
    $examesMedia = array();

    foreach ($arrayExames as $key => $exame) {
        if ($exame['Exame'] == 'COLONOSCOPIA') {
            $exames['colonoscopia'] = (int)$exame['Quantidade'];
        } else if ($exame['Exame'] == 'ECOCARDIOGRAMA') {
            $exames['ecocardiograma'] = (int)$exame['Quantidade'];
        } else if ($exame['Exame'] == 'ENDOSCOPIAS') {
            $exames['endoscopia'] = (int)$exame['Quantidade'];
        } else if ($exame['Exame'] == 'TOMOGRAFIA') {
            $exames['tomografia'] = (int)$exame['Quantidade'];
        } else if ($exame['Exame'] == 'ULTRASSOM') {
            $exames['ultrasson'] = (int)$exame['Quantidade'];
        } else if ($exame['Exame'] == 'BRONCOSCOPIA') {
            $exames['broncoscopia'] = (int)$exame['Quantidade'];
        } else if ($exame['DESCRIÇÃO'] == 'LABORATÓRIO DE ANÁLISES CLÍNICAS') {
            $exames['laboratorio'] = (int)$exame['Quantidade'];
        } else if ($exame['DESCRIÇÃO'] == 'RADIOLOGIA') {
            $exames['raiox'] += (int)$exame['Quantidade'];
        }
    }
    foreach ($arrayExamesMedia as $key => $exame) {
        if ($exame['Exame'] == 'COLONOSCOPIA') {
            $examesMedia['colonoscopia'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['Exame'] == 'ECOCARDIOGRAMA') {
            $examesMedia['ecocardiograma'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['Exame'] == 'ENDOSCOPIAS') {
            $examesMedia['endoscopia'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['Exame'] == 'TOMOGRAFIA') {
            $examesMedia['tomografia'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['Exame'] == 'ULTRASSOM') {
            $examesMedia['ultrasson'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['Exame'] == 'BRONCOSCOPIA') {
            $examesMedia['broncoscopia'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['DESCRIÇÃO'] == 'LABORATÓRIO DE ANÁLISES CLÍNICAS') {
            $examesMedia['laboratorio'] = (int)round((float)$exame['Média diária do Ano Anterior']);
        } else if ($exame['DESCRIÇÃO'] == 'RADIOLOGIA') {
            $examesMedia['raiox'] += (int)round((float)$exame['Média diária do Ano Anterior']);
        }
    }
    executeDiario($exames, $subItems, $params);
    executeMedia($examesMedia, $itemsMedia, $params);
    return 0;
}
function populateEnfermarias($queryEnfermarias, $params)
{
    $subItems = array(
        'internacoes' => 138, 'altas' => 137, 'obitosAte' => 101, 'obitosApos' => 102, 'consultas' => 42,
        'neuroOCUPADO' => 12, 'neuroLIVRE' => 13, 'neuroDESATIVADO' => 16,
        'neuroBLOQUEADO' => 14,
        'ortoOCUPADO' => 48, 'ortoLIVRE' => 49, 'ortoDESATIVADO' => 162,
        'ortoBLOQUEADO' => 162,
        'cliCirOCUPADO' => 179, 'cliCirLIVRE' => 181, 'cliCirDESATIVADO' => 176, 'cliCirBLOQUEADO' => 175,
        'cliMedOCUPADO' => 77, 'cliMedLIVRE' => 78, 'cliMedDESATIVADO' => 81, 'cliMedBLOQUEADO' => 79,
        'pedOCUPADO' => 86, 'pedLIVRE' => 87, 'pedDESATIVADO' => 90, 'pedBLOQUEADO' => 88,
        'utiaOCUPADO' => 55, 'utiaLIVRE' => 56, 'utiaDESATIVADO' => 59, 'utiaBLOQUEADO' => 57,
        'utipedOCUPADO' => 94, 'utipedLIVRE' => 95, 'utipedDESATIVADO' => 98, 'utipedBLOQUEADO' => 96,
        'verdeOCUPADO' => 153, 'verdeLIVRE' => 161, 'verdeDESATIVADO' => 156, 'verdeBLOQUEADO' => 154,
        'amarelaOCUPADO' => 142, 'amarelaLIVRE' => 144, 'amarelaDESATIVADO' => 147, 'amarelaBLOQUEADO' => 145
    );


    // $items = array('altas' => 6, 'internacoes' => 5);

    $resultAghu = executeEnfermarias($queryEnfermarias, $params);
    $arrayEnfermarias = formatArray($resultAghu, 'internacoes');
    if ($params['datai'] == date('Y-m-d') || $params['dataCronJob'])
        $arrayEnfermariasRelatorio = formatArray($resultAghu, 'relatorio_enfermarias');

    $enfermarias = array();
    $enfermariasRelatorio = array();
    $enfermarias['internacoes'] = (int)$arrayEnfermarias[0]['Atendimento diario'];
    $enfermarias['obitosAte'] = (int)$arrayEnfermarias[0]['Obitos em ate 24h'];
    $enfermarias['obitosApos'] = (int)$arrayEnfermarias[0]['Obitos após 24h'];
    $enfermarias['altas'] = (int)$arrayEnfermarias[0]['Altas do dia'];

    if ($arrayEnfermarias) {
        foreach ($arrayEnfermariasRelatorio as $key => $value) {
            if ($value['Unidade Funcional'] == "68") {
                $unidade = 'neuro';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "69") {
                $unidade = 'orto';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "67") {
                // $unidade = 'ped';
                // $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "8") {
                $unidade = 'cliCir';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if (
                $value['Unidade Funcional'] == "64"
            ) {
                $unidade = 'cliMed';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "11") {
                $unidade = 'utia';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "13") {
                $unidade = 'utiped';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "98") {
                $unidade = 'amarela';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "100") {
                $unidade = 'verde';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            } else if ($value['Unidade Funcional'] == "66") {
                $unidade = 'obsPed';
                $enfermariasRelatorio[$unidade . $value['situacao']] = $value['Quantidade'];
            }
        }
        executeDiario($enfermariasRelatorio, $subItems, $params);
    }
    executeDiario($enfermarias, $subItems, $params);
    return 0;
}
function populateConsultasAmbulatoriais($queryConsultasAmbulatoriais, $params)
{
    $subItems = array('consultasAmb' => 4, 'consultasOnco' => 141);
    $items = array('consultasAmb' => 4, 'consultasOnco' => 1);
    $resultAghu = executeConsultasAmbulatoriais($queryConsultasAmbulatoriais, $params);
    $arrayConsultas = formatArray($resultAghu, 'consultas');
    $arrayConsultasMedia = formatArray($resultAghu, 'consultasMedia');
    $consultas = array();
    $consultasMedia = array();
    if ($arrayConsultas != null) {
        foreach ($arrayConsultas as $value) {
            if ($value['Codigo Unidade'] == 77) {
                $consultas['consultasOnco'] += $value['Quantidade'];
            } else if ($value['Codigo Unidade'] == 75) {
                $consultas['consultasAmb'] += $value['Quantidade'];
            }
        }
    }
    foreach ($arrayConsultasMedia as $value) {
        if ($value['Codigo Unidade'] == 77) {
            $consultasMedia['consultasOnco'] += $value['Média diária do ano Anterior'];
        } else if ($value['Codigo Unidade'] == 75) {
            $consultasMedia['consultasAmb'] += $value['Média diária do ano Anterior'];
        }
    }
    executeDiario($consultas, $subItems, $params);
    executeMedia($consultasMedia, $items, $params);
    return 0;
}
function executeMedia($itemMedia, $itemsMedia, $params)
{
    $ano = date('Y', strtotime($params['datai'] . '-1 year'));
    $stmt = $GLOBALS['db']->prepare($GLOBALS['query_SelectMedia']);
    foreach ($itemMedia as $key => $value) {
        if ($itemsMedia[$key] && $itemsMedia[$key] != '') {
            $stmt->execute(array("itemMedia" => (int)$itemsMedia[$key], "ano" => $ano));
            if ($stmt->rowCount() >= 1) {
                /** 
                 * Descomentar caso queira atualizar as médias do ano passado
                 * 
                 */
                // $stmt3 = $GLOBALS['db']->prepare($GLOBALS['query_MediaUpdate']);
                // $stmt3->bindValue(':valor', (int)$value, PDO::PARAM_INT);
                // $stmt3->bindValue(':itemsMedia', (int)$itemsMedia[$key], PDO::PARAM_INT);
                // $stmt3->bindValue(':ano', (int)$ano, PDO::PARAM_INT);
                // $stmt3->execute();
                // echo "update media";
                // var_dump($stmt3->fetchAll(PDO::FETCH_ASSOC));
            } else {
                $stmt2 = $GLOBALS['db']->prepare($GLOBALS['query_MediaInsert']);
                $stmt2->bindValue(':itemsMedia', (int)$itemsMedia[$key], PDO::PARAM_INT);
                $stmt2->bindValue(':valor', (int)$value, PDO::PARAM_STR);
                $stmt2->bindValue(':ano', (int)$ano, PDO::PARAM_INT);
                $stmt2->execute();
                var_dump($stmt2->fetchAll());
            }
        }
    }
}

function executeDiario($item, $subItems, $params)
{
    $stmt = $GLOBALS['db']->prepare($GLOBALS['query_Select']);
    foreach ($item as $key => $val) {
        if ($subItems[$key] && $subItems[$key] != '') {
            $stmt->execute(array('datai' => $params['datai'], ':idSubItem' => (int)$subItems[$key]));
            if (($stmt->rowCount()) >= 1) {

                $query = $GLOBALS['query_Update'];
                $stmt3 = $GLOBALS['db']->prepare($query);
                $stmt3->bindValue(':valor', $val, PDO::PARAM_STR);
                $stmt3->bindValue(':idSubItem', $subItems[$key], PDO::PARAM_STR);
                $stmt3->bindValue(':dataLanc', $params['datai'], PDO::PARAM_STR);
                try {
                    $stmt3->execute();
                } catch (PDOException $e) {
                    echo $e;
                }
            } else {
                $query = $GLOBALS['query_Insert'];
                $stmt2 = $GLOBALS['db']->prepare($query);
                $stmt2->bindValue(':idSubItem', $subItems[$key], PDO::PARAM_STR);
                $stmt2->bindValue(':valor', $val, PDO::PARAM_STR);
                $stmt2->bindValue(':datalanc', $params['datai'], PDO::PARAM_STR);
                try {
                    $stmt2->execute();
                } catch (PDOException $e) {
                    echo "Error " . $e;
                }
            }
        }
    }
    return 0;
}

function formatArray($array, $campo)
{
    if ($array[$campo]) {
        foreach ($array[$campo] as $key => $value) {
            foreach ($value as $key2 => $value2) {
                $arrayFormatado[$key][$key2] =  $value2;
            }
        }
        return $arrayFormatado;
    }
    return null;
}
// && date('H:i', strtotime($params['dataCronJob'])) == '11:20'
if (!$data['uf']) {
    echo "Iniciando populate\n\n";
    try {
        echo "Iniciando populate CC - " . date('Y-m-d H:i:s') . "\n";
        populateCC($queryAghuCC, $params);
        echo "Populado Centro Cirurgico na data de  " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n\n";
    } catch (Exception $e) {
        "Erro ao popular Centro Cirúrgico na data de  " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n" . $e;
    }
    try {
        echo "Iniciando Enfermarias - " . date('Y-m-d H:i:s') . "\n";
        populateEnfermarias($queryAghuEnfermarias, $params);
        echo "Populado Enfermarias na data de " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n\n";
    } catch (Exception $e) {
        echo "Erro ao popular enfermarias na data de " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n" . $e;
    }
    try {
        echo "Iniciando populate ApoioDiag - " . date('Y-m-d H:i:s') . "\n";
        populateApoioDiag($queryAghuApoioDiag, $params);
        echo "Populado ApoioDiagnostico na data de " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n\n";
    } catch (Exception $e) {
        echo "Erro ao popular Apoio diag na data de  " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n" . $e;
    }
    try {
        echo "Iniciando populate Consultas Ambulatoriais - " . date('Y-m-d H:i:s') . "\n";
        populateConsultasAmbulatoriais($queryConsultasAmbulatoriais, $params);
        echo "Populado Consultas Ambulatoriais na data de  " . $params['datai'] . " - ÀS  --" . date('Y-m-d H:i:s') . "\n\n";
    } catch (Exception $e) {
        echo "Erro ao popular consultas Ambulatoriais na data de " . $params['datai'] . " - ÀS  --" .  date('Y-m-d H:i:s') . "\n " . $e;
    }
    echo "Terminado - " . date('Y-m-d H:i:s') . "\n";
} else {
    switch ($data['uf']) {
        case 'cc':
            populateCC($queryAghuCC, $params);
            echo "Populado CC com sucesso - " . date('Y-m-d H:i:s');

            break;
        case 'apoiodiag':
            populateApoioDiag($queryAghuApoioDiag, $params);
            echo "Populado apoiodiag com sucesso - " . date('Y-m-d H:i:s');

            break;
        case 'enfermarias':
            populateEnfermarias($queryAghuEnfermarias, $params);
            echo "Populado enfermarias com sucesso - " . date('Y-m-d H:i:s');

            break;
        case 'con_amb':
            populateConsultasAmbulatoriais($queryConsultasAmbulatoriais, $params);
            echo "Populado consultas Amb com sucesso - " . date('Y-m-d H:i:s');
            break;
    }
}
