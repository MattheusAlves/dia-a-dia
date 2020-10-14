<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
include_once("/var/www/html/dia-a-dia/servidor/DbPDO.class.php");
include_once('/var/www/html/dia-a-dia/servidor/queries.php');;
if ($_SERVER && $_SERVER['REQUEST_METHOD'] != 'POST') {
    // ini_set('display_errors', 1);
    // error_reporting(E_ALL);
    return 0;
} else {
    $data = json_decode(file_get_contents("php://input"), TRUE);
    if ($data['populate'] && $data['populate'] == 1) {

        return 0;
    }
}
if ($data) {
    try {
        $uf = $data['uf'];
        $amb = $data['amb'];

        $data_format = str_replace('/', '-', $data['datai']);
        $dataf_format = str_replace('/', '-', $data['dataf']);
        $datai = date('Y-m-d', strtotime($data_format));

        if ($dataf_format != null) {
            $dataf = date("Y-m-d", strtotime($dataf_format . '+1 day'));
        } else {
            $dataf = date("Y-m-d", strtotime($datai . '+1 day'));
        }
        $dataiMediaDiaria = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-' . '01' . '-01'));
        $datafInter = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-01'));
        $datafMediaDiaria = date('Y-m-d', strtotime(date('Y', strtotime($datai . '-1 year')) . '-12-' . date('t', strtotime($datafInter))));
        $datas = array('datai' => $datai, 'dataf' => $dataf, 'dataiMedia' => $dataiMediaDiaria, 'datafMedia' => $datafMediaDiaria);
    } catch (Exception $e) {
        echo ("Error, " . $e);
        return 1;
    }
    switch ($uf) {
        case 'cc':
            $query = array(
                'query_CC' => $query_CC,
                'query_cirg_porte' => $query_cirg_porte,
                'query_cirg_porte_media' => $query_cirg_porte_media,
                'query_media_diaria' => $query_media_diaria,
                'query_CCAgendadas' => $query_CCAgendadas
            );
            echo json_encode(executeCC($query, $datas, $amb));
            break;
        case 'apoiodiag':
            $query = array(
                'query_ExamesRadiologia' => $query_ExamesRadiologia,
                'query_ExamesRadiologiaMedia' => $query_ExamesRadiologiaMedia
            );
            echo json_encode(executeApoioDiag($query, $datas));

            break;
        case 'enfermarias':
            $query = array(
                'query_EnfermariasInternacoes' => $query_EnfermariasInternacoes,
                'query_Enfermarias' => $query_Enfermarias
            );
            echo json_encode(executeEnfermarias($query, $datas));

            break;
        case 'con_amb':
            $query = array(
                'query_ConsultasAmbulatoriais' => $query_ConsultasAmbulatoriais,
                'query_ConsultasAmbulatoriaisMedia' => $query_ConsultasAmbulatoriaisMedia,
                'query_ConsultasPSAPSI' => $query_ConsultasPSAPSI,
            );
            echo json_encode(executeConsultasAmbulatoriais($query, $datas));
    }
}

function encode_array($value)
{
    // return utf8_decode($value);
    return $value;
}
function executeQuery($query, $params, $dbIndicator = false)
{
    if ($dbIndicator == 'matrix') {
        $db = new DbPDO('MATRIX');
        $queryMatrix = "SELECT
        --      P.DATASISTEMA,
        --      P.ORIGEM,
        --      P.CLINICA,
        --      P.CONVENIO,
        --        PP.CODIGO AS EXAME,
        --      PRO.CODIGO,
        --      CS.CODIGOSERVICO AS PROC_SUS,
        --      PRO.CODIGOFATURAMENTO,
        --      PP.AREA,
        --      PP.UNIDADEPRODUTIVA
        --        MONTH(TO_DATE(PP.DATALIBERACAOCLINICA,'YYYYMMDD')) AS MES,
        --      ) AS MES_COMPETENCIA
             -- PP.DATALIBERACAOCLINICA AS REALIZACAO,
              COUNT(*) AS TOTAL
        FROM
                LMPEDIDO P
                INNER JOIN LMPEDIDOPROCEDIMENTO PP
                        ON PP.NUMEROPEDIDO=P.NUMEROPEDIDO
                LEFT JOIN LDPROCEDIMENTO PRO
                        ON PRO.CODIGO=PP.CODIGO
                LEFT JOIN LDCODIGOSERVICOPROCEDIMENTO CS
                        ON PRO.CODIGOFATURAMENTO = CS.CODIGOFATURAMENTO
        WHERE
                PP.DATALIBERACAOCLINICA >= '" . $params[':datai'] . "' AND PP.DATALIBERACAOCLINICA < '" .  $params[':dataf'] . "'
        --       AND (P.ORIGEM='HMMG' AND P.CLINICA<>'ONCO' AND P.CLINICA<>'AMBU') -- AREAS AMBULATORIAIS
                AND PP.UNIDADEPRODUTIVA IN ('HMMG','RS','UNILAB')  -- EXAMES REALIZADOS PELO HMMG E TERCEIRIZADOS (RS e UNILAB)
                AND CS.TABELACODIGOSERVICO IN ('MXSUS','SUS')   -- SOMENTE TABELA SUS
                GROUP BY PP.DATALIBERACAOCLINICA
                ORDER BY PP.DATALIBERACAOCLINICA";
        $stmt = $db->prepare($queryMatrix);

        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            echo "error";
            echo "Exception Occurred :" . $e->getMessage();
        }
        return 0;
    }
    if ($dbIndicator == 'matrixMedia') {
        $db = new DbPDO('MATRIX');
        $queryMatrix = "SELECT
        --      P.DATASISTEMA,
        --      P.ORIGEM,
        --      P.CLINICA,
        --      P.CONVENIO,
        --        PP.CODIGO AS EXAME,
        --      PRO.CODIGO,
        --      CS.CODIGOSERVICO AS PROC_SUS,
        --      PRO.CODIGOFATURAMENTO,
        --      PP.AREA,
        --      PP.UNIDADEPRODUTIVA
        --        MONTH(TO_DATE(PP.DATALIBERACAOCLINICA,'YYYYMMDD')) AS MES,
        --      ) AS MES_COMPETENCIA
             -- PP.DATALIBERACAOCLINICA AS REALIZACAO,
             to_char(round(count(*)/ 365,0),'FM999999990') as \"Media diaria\"
        FROM
                LMPEDIDO P
                INNER JOIN LMPEDIDOPROCEDIMENTO PP
                        ON PP.NUMEROPEDIDO=P.NUMEROPEDIDO
                LEFT JOIN LDPROCEDIMENTO PRO
                        ON PRO.CODIGO=PP.CODIGO
                LEFT JOIN LDCODIGOSERVICOPROCEDIMENTO CS
                        ON PRO.CODIGOFATURAMENTO = CS.CODIGOFATURAMENTO
        WHERE
                PP.DATALIBERACAOCLINICA BETWEEN'" . $params[':datai'] . "' AND  '" .  $params[':dataf'] . "'
        --       AND (P.ORIGEM='HMMG' AND P.CLINICA<>'ONCO' AND P.CLINICA<>'AMBU') -- AREAS AMBULATORIAIS
                AND PP.UNIDADEPRODUTIVA IN ('HMMG','RS','UNILAB')  -- EXAMES REALIZADOS PELO HMMG E TERCEIRIZADOS (RS e UNILAB)
                AND CS.TABELACODIGOSERVICO IN ('MXSUS','SUS')   -- SOMENTE TABELA SUS
                --GROUP BY PP.DATALIBERACAOCLINICA
                ORDER BY PP.DATALIBERACAOCLINICA";
        $stmt = $db->prepare($queryMatrix);
        try {
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (PDOException $e) {
            echo "error";
            echo "Exception Occurred :" . $e->getMessage();
        }
        return 0;
    }
    $db = new DbPDO('AGHU');
    $stmt = $db->prepare($query);
    if ($params) {
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }

    try {
        $stmt->execute();
    } catch (PDOException $e) {
        echo "Exception Occurred :" . $e->getMessage();
    }
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($result == null) {
        $json_error = (object) array('message' => 'Nenhum valor retornado');
        // echo (json_encode($json_error));
        return 0;
    }

    return $result;
}

function executeCC($query, $datas, $amb)
{
    $amb == true ? $unf = 122 : $unf = 71;

    $params = array(":datai" => $datas['datai'], ":dataf" => $datas['dataf'], ":dataf2" => $datas['dataf'], ":unf" => $unf);
    $params2 = array(":datai" => $datas['datai'], ":dataf" => $datas['dataf'], ":unf" => $unf);
    $params_media = array(
        ':datai' => $datas['dataiMedia'], ":dataf" => $datas['datafMedia'],  ":unf" => $unf,
    );

    $cirurgias = executeQuery($query['query_CC'], $params);
    $cirurgias_por_porte = executeQuery($query['query_cirg_porte'], $params2);
    $cirurgias_por_porte_media = executeQuery($query['query_cirg_porte_media'], $params_media);
    $cirurgias_media_diaria = executeQuery($query['query_media_diaria'], $params_media);
    $cirurgias_total_agendadas = executeQuery($query['query_CCAgendadas'], $params2);
    $cirurgias_total_agendadas_media = executeQuery($query['query_CCAgendadas'], $params_media);

    $json = array(
        'cirurgias' => $cirurgias,
        'cirurgias_por_porte' => $cirurgias_por_porte,
        'cirurgias_por_porte_media' => $cirurgias_por_porte_media,
        'cirurgia_media_diaria' => $cirurgias_media_diaria,
        'cirurgias_total_agendadas' => $cirurgias_total_agendadas,
        'cirurgias_total_agendadas_media' => $cirurgias_total_agendadas_media
    );

    return $json;
}
function executeApoioDiag($query, $datas)
{
    $params = array(":datai" => $datas['datai'], ":dataf" => $datas['dataf']);
    $params2 = array(":datai" => $datas['dataiMedia'], ":dataf" => $datas['datafMedia']);

    $examesLaboratorio = executeQuery(null, $params, 'matrix');
    $examesRadiologia = executeQuery($query['query_ExamesRadiologia'], $params);
    $examesLaboratorioMedia = executeQuery(null, $params2, 'matrixMedia');
    $examesRadiologiaMedia = executeQuery($query['query_ExamesRadiologiaMedia'], $params2);

    $exames = array();
    $examesMedia = array();

    $examesLab[0]['DESCRIÇÃO'] = 'LABORATÓRIO DE ANÁLISES CLÍNICAS';
    $examesLab[0]['Exame'] = '';
    $examesLab[0]['Quantidade'] = $examesLaboratorio[0]['TOTAL'];
    $examesLabMedia[0]['DESCRIÇÃO'] = 'LABORATÓRIO DE ANÁLISES CLÍNICAS';
    $examesLabMedia[0]['Exame'] = '';
    $examesLabMedia[0]['Média diária do Ano Anterior'] = $examesLaboratorioMedia[0]['Media diaria'];

    $examesUnformatted = array_merge($examesLab, $examesRadiologia);
    $examesMediaUnformatted = array_merge($examesLabMedia, $examesRadiologiaMedia);

    foreach ($examesUnformatted as $key  => $value) {
        $exames[$key] = $value;
    }
    foreach ($examesMediaUnformatted as $key => $value) {
        $examesMedia[$key] = $value;
    }
    $json = array(
        "exames" => $exames,
        "examesMedia" => $examesMedia
    );
    // var_dump($json);
    return $json;
}
function executeEnfermarias($query, $datas)
{
    $params = array(
        ":dataiMedia" => $datas['dataiMedia'], ":datafMedia" => $datas['datafMedia'],
        ":dataiMedia" => $datas['dataiMedia'], ":datafMedia" => $datas['datafMedia'],
        ":dataiMedia" => $datas['dataiMedia'], ":datafMedia" => $datas['datafMedia'],
        ":datai2" => $datas['datai'], ":dataf2" => $datas['dataf'],
        ":datai" => $datas['datai'], ":dataf" => $datas['dataf'],
        ":datai" => $datas['datai'], ":dataf" => $datas['dataf'],

    );
    $internacoes = executeQuery($query['query_EnfermariasInternacoes'], $params);

    if ($datas['datai'] == date('Y-m-d', strtotime('-1 day')) || $datas['datai'] == date('Y-m-d')) {
        $relatorio = executeQuery($query['query_Enfermarias'], null);
        $json = array(
            'internacoes' => $internacoes,
            'relatorio_enfermarias' => $relatorio
        );
    } else {
        $json = array(
            'internacoes' => $internacoes
        );
    }

    return $json;
}
function executeConsultasAmbulatoriais($query, $datas)
{
    $params = array(
        ':datai' => $datas['datai'],
        ':dataf' => $datas['dataf']
    );
    $paramsMedia = array(
        ':dataiMedia' => $datas['dataiMedia'],
        ':datafMedia' => $datas['datafMedia']
    );
    $consultasAmb = executeQuery($query['query_ConsultasAmbulatoriais'], $params);
    $consultasAmbMedia = executeQuery($query['query_ConsultasAmbulatoriaisMedia'], $paramsMedia);
    if ($query['query_ConsultasPSAPSI']) {
        $consultasPSAPSI = executeQuery($query['query_ConsultasPSAPSI'], $params);
    }
    if ($consultasPSAPSI) {
        $json = array(
            'consultas' => $consultasAmb,
            'consultasMedia' => $consultasAmbMedia,
            'consultasPSAPSI' => $consultasPSAPSI
        );
    } else {
        $json = array(
            'consultas' => $consultasAmb,
            'consultasMedia' => $consultasAmbMedia,
        );
    }
    return $json;
}
