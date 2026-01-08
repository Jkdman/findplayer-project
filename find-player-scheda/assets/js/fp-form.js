document.addEventListener("DOMContentLoaded", function () {

    const wrapper = document.getElementById("discipline-wrapper");
    const btnAdd = document.getElementById("aggiungi-disciplina");

    console.log("JS loaded, wrapper:", wrapper, "button:", btnAdd);

    if (wrapper && btnAdd) {
        btnAdd.addEventListener("click", function () {
            console.log("Aggiungi disciplina premuto");
            addDisciplineRow();
        });
    }
const btnAll = document.getElementById("aggiungi-tutti");

if (wrapper && btnAll) {
    btnAll.addEventListener("click", function () {
        console.log("Inserimento massivo sport");
        addAllSports();
    });
}
function addSpecificSports(lista) {
    lista.forEach(sport => {
        addDisciplineRow(sport);
    });
}

function addDisciplineRow(preselect = '') {
    if (!wrapper) return;

    const block = document.createElement("div");
    block.classList.add("fp-disciplina-row");

    block.innerHTML =
        '<select name="sport[]" class="fp-sport-select">' +
        SPORT_OPTIONS_HTML +
        '</select>' +
        '<input type="range" name="livello[]" min="1" max="10" value="1" class="fp-livello-slider">' +
        '<span class="fp-livello-val">1</span>' +
        '<button type="button" class="fp-remove-row">✖</button>';

    wrapper.appendChild(block);

    const select = block.querySelector("select");
    if (preselect) select.value = preselect;

    const slider = block.querySelector(".fp-livello-slider");
    const val = block.querySelector(".fp-livello-val");
    const remove = block.querySelector(".fp-remove-row");

    slider.addEventListener("input", () => {
        val.textContent = slider.value;
    });

    remove.addEventListener("click", () => {
        block.remove();
    });
}

function addAllSports() {
    const temp = document.createElement("div");
    temp.innerHTML = '<select>' + SPORT_OPTIONS_HTML + '</select>';

    const options = temp.querySelectorAll("option");

    options.forEach(opt => {
        if (!opt.value) return; // salta "Seleziona..."
        addDisciplineRow(opt.value);
    });
}
// ⚠️ SOLO PER TEST
addAllSports();
});