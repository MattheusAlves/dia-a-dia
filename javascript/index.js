$('document').ready(async function () {
    await $.getScript("./javascript/jquery.inputmask.min.js")
    // await $.getScript('./javascript/axios.min.js')
    $(".input-date").inputmask("99/99/9999", { "placeholder": 'DD/MM/AAAA' });  //static mask
    const form = document.getElementById('form');
    const confirmbtn = document.getElementById('confirmbtn')
    const cancelbtn = document.getElementById('cancelbtn')


    //events listeners
    form.addEventListener('submit', (e) => {
        if (e.submitter.id == 'search') {
            handleSubmit(e)
        } else {
            e.preventDefault();
            document.getElementById('modal-message').innerHTML = `Tem certeza que deseja popular o dia-a-dia na data de ${e.target.datai.value}?`
            document.getElementById('modal').style.display = 'block'
        }

    })

    confirmbtn.addEventListener('click', () => {
        document.getElementById('modal-content').style.WebkitAnimation = 'modalOut .5s'
        document.getElementById('modal-content').addEventListener('webkitAnimationEnd', function listenerConfirm() {
            document.getElementById('modal').style.display = 'none'
            handleInsert()
            document.getElementById('modal-content').removeEventListener('webkitAnimationEnd', listenerConfirm, false)
            document.getElementById('modal-content').style.WebkitAnimation = 'modal .4s ease-out'
        })
    })

    cancelbtn.addEventListener('click', (e) => {
        document.getElementById('modal-content').style.WebkitAnimation = 'modalOut .5s'
        document.getElementById('modal-content').addEventListener('webkitAnimationEnd', function listenerCancel() {
            document.getElementById('modal').style.display = 'none'
            document.getElementById('modal-content').removeEventListener('webkitAnimationEnd', listenerCancel, false)
            document.getElementById('modal-content').style.WebkitAnimation = 'modal .4s ease-out'
        })

    })

})

function handleInsert() {
    // e.preventDefault()

    document.getElementById("loaderInsert").style.display = 'block'
    document.getElementById('modal').style.display = 'none'

    const datai = document.getElementsByName('datai')[0].value
    const uf = document.getElementsByName('uf')[0].value
    console.log(uf);
    axios.post('./servidor/populateDb.php', JSON.stringify({
        datai,
        uf,
        populate: 1
    })).then((response) => {
        document.getElementById("loaderInsert").style.display = 'none'
        console.log(response)
        alert("Populado com sucesso")
    })
        .catch((error) => {
            document.getElementById("loaderInsert").style.display = 'none'
            console.log(error)
            alert("Erro ao popular, consulte o administrador. ", error)
            return
        })
}

function handleSubmit(e) {
    e.preventDefault();

    document.getElementById('loaderSearch').style.display = 'block'
    document.getElementById("insertButton").style.display = 'none'

    const datai = e.target.datai.value
    const dataf = e.target.dataf.value ? e.target.dataf.value : undefined
    let uf = e.target.uf.value
    axios({
        headers: { 'Content-Type': 'application/json' },
        method: 'post',
        url: './servidor/index.php',
        data: {
            datai: datai,
            dataf: dataf ? dataf : null,
            uf: uf == 'cc_amb' ? 'cc' : uf,
            amb: uf == 'cc_amb' ? true : false
        }
    }).then((result) => {
        // console.log(result)
        document.getElementById("loaderSearch").style.display = 'none'
        document.getElementById('table').style.visibility = "visible"
        const formatted = JSON.parse(result.data.trim())
        if (formatted.message) {
            alert("Nenhum valor retornado")
            return 0;
        }
        document.getElementById("insertButton").style.display = 'flex'
        if (formatted.cirurgias) {
            const length = Object.keys(formatted).length
            if (formatted.cirurgias) {
                createTable(formatted.cirurgias, uf, 0, length)
                createTable(formatted.cirurgias_por_porte, uf, 1, length)
            }
            if (!document.getElementById('info')) {
                const info = document.createTextNode("Total por Porte(Média Diária do ano anteiror)")
                let h2 = document.createElement('h2')
                let div = document.createElement('div')
                h2.appendChild(info)
                div.appendChild(h2)
                div.setAttribute('id', 'info')
                document.getElementById("table").appendChild(div)
                console.log("exec")
            }
            createTable(formatted.cirurgias_por_porte_media, uf, 3, length)
            createTable(formatted.cirurgia_media_diaria, uf, 4, length)
        } else if (formatted.relatorio_enfermarias || formatted.internacoes) {
            const length = Object.keys(formatted).length
            formatted.internacoes ? createTable(formatted.internacoes, uf, 0, length) : ''
            formatted.relatorio_enfermarias ? createTable(formatted.relatorio_enfermarias, uf, 1, length) : ''
        } else if (formatted.exames) {
            formatted.exames ? createTable(formatted.exames, uf, 0, 2) : ''
            createTable(formatted.examesMedia, uf, 1, 2)
        } else if (formatted.consultas) {
            const keys = Object.keys(formatted)
            keys.length
            formatted.consultas ? createTable(formatted.consultas, uf, 0, 3) : ''
            createTable(formatted.consultasMedia, uf, 1, 3)
            formatted.consultasPSAPSI ? createTable(formatted.consultasPSAPSI, uf, 2, 3) : ''
        }else{
            alert('Nenhum registro encontrado para a data informada')
        }

    }).then(() => {
        if (!document.getElementById("clean")) {
            let btn = document.createElement("BUTTON")
            btn.innerHTML = "Limpar"
            btn.setAttribute("class", 'input button-submit')
            btn.setAttribute("id", 'clean')
            btn.addEventListener("click", (e) => {
                e.preventDefault()
                document.getElementById('table').innerHTML = ''
                document.getElementById('form').removeChild(btn)
                document.getElementById("insertButton").style.display = 'none'
                document.getElementById('table').style.visibility = 'hidden'
            })
            document.getElementById('form').append(btn)
        }
    }).catch((error) => {
        console.log(error)
        alert("Erro ao pesquisar, consulte o administrador.", error)
        return;
    })

}

function createTable(data, uf, tableRange, quantity) {
    var table = document.createElement('table')
    table.setAttribute('class', `table-01`)
    uf ? table.setAttribute('id', `${uf}`) : ''
    let thead = table.createTHead()
    var tr = thead.insertRow()
    Object.keys(data[0]).forEach((name) => {
        var th = document.createElement('th')
        th.innerHTML = name
        tr.appendChild(th)
    })
    let tbody = table.createTBody()
    data.map((value) => {
        let row = table.insertRow()
        for (let item in value) {
            let cell = row.insertCell()
            let text = document.createTextNode(value[item])
            cell.appendChild(text)
        }
        tbody.appendChild(row)

    })
    var div = document.getElementById("table")
    if (div.firstChild && div.firstChild.id != uf) {
        div.innerHTML = ''
        div.append(table)
        return 0
    }
    if (div.childNodes.length >= quantity) {
        div.replaceChild(table, div.childNodes[tableRange])
        return 0
    } else
        div.append(table)
}