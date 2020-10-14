<?php
$query_Select = <<<QUERY
    SELECT * FROM informacao.diaadia where idsubitem = :idSubItem and datalanc = :datai
QUERY;
$query_SelectMedia = <<<QUERY_SELECT
    SELECT * FROM informacao.mediadiaria where iditem = :itemMedia and ano = :ano
QUERY_SELECT;

$query_Update = <<<QUERY
    UPDATE INFORMACAO.DIAADIA SET VALOR=:valor WHERE idsubitem= :idSubItem and datalanc = :dataLanc
QUERY;

$query_Insert = <<<QUERY_INSERT
    INSERT INTO informacao.diaadia(idsubitem,valor,datalanc,datapublic) 
                   VALUES(:idSubItem,:valor,:datalanc,to_char(now(),'yyyy-mm-dd')::DATE)
QUERY_INSERT;

$query_MediaInsert = <<<QUERY_INSERT
    INSERT INTO informacao.mediadiaria(iditem,valor,ano) values (:itemsMedia,:valor,:ano)
QUERY_INSERT;
$query_MediaUpdate = <<<QUERY_UPDATE
    update informacao.mediadiaria set valor = :valor where iditem = :itemsMedia and ano = :ano
QUERY_UPDATE;

