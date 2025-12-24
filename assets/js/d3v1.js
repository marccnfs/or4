const svg = d3.select("svg");
const width = window.innerWidth;
const height = window.innerHeight;
svg.attr("width", width).attr("height", height);

const controls = d3.select("#controls");
const input = d3.select("#user-input");
const button = d3.select("#generate-button");

const clusters = [];
let particles = [];

// Génère des clusters et des particules
function generateClustersFromKeywords(keywords) {
    clusters.length = 0;
    particles.length = 0;

    // Supprime les anciens éléments
    svg.selectAll("*").remove();

    // Ajoute chaque mot-clé en tant que cluster
    keywords.forEach((keyword) => {
        clusters.push(keyword);

        // Particules associées au cluster
        for (let i = 0; i < 20; i++) {
            particles.push({
                cluster: keyword,
                x: keyword.x + Math.random() * 100 - 50,
                y: keyword.y + Math.random() * 100 - 50,
                vx: Math.random() * 2 - 1,
                vy: Math.random() * 2 - 1,
            });
        }
    });

    drawClusters();
    drawParticles();
    addLines();
}

// Dessine les clusters
function drawClusters() {
    const clusterGroup = svg.selectAll(".cluster")
        .data(clusters)
        .enter()
        .append("g")
        .attr("class", "cluster");

    // Ajoute des cercles pour les clusters
    clusterGroup.append("circle")
        .attr("cx", d => d.x)
        .attr("cy", d => d.y)
        .attr("r", 0) // Commence à 0 pour l'animation
        .attr("fill", d => d.color)
        .attr("opacity", 0.7)
        .transition()
        .duration(1000)
        .attr("r", d => d.size);

    // Ajoute des étiquettes pour les clusters
    clusterGroup.append("text")
        .attr("x", d => d.x)
        .attr("y", d => d.y - d.size - 10)
        .text(d => d.keyword)
        .attr("text-anchor", "middle")
        .attr("fill", "white")
        .attr("opacity", 0)
        .transition()
        .duration(1000)
        .attr("opacity", 1);
}

// Dessine les particules
function drawParticles() {
    svg.selectAll(".particle")
        .data(particles)
        .enter()
        .append("circle")
        .attr("class", "particle")
        .attr("cx", d => d.x)
        .attr("cy", d => d.y)
        .attr("r", 0) // Commence à 0 pour l'animation
        .attr("fill", "lightblue")
        .attr("opacity", 0.8)
        .transition()
        .duration(1000)
        .attr("r", 5);
}

// Ajoute des lignes reliant les particules aux clusters
function addLines() {
    svg.selectAll(".line")
        .data(particles)
        .enter()
        .append("line")
        .attr("class", "line")
        .attr("x1", d => d.x)
        .attr("y1", d => d.y)
        .attr("x2", d => d.cluster.x)
        .attr("y2", d => d.cluster.y)
        .attr("stroke", "white")
        .attr("stroke-width", 1)
        .attr("opacity", 0.5);
}

// Animation des particules
function animateParticles() {
    particles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;

        // Retourne les particules dans la limite de leur cluster
        if (Math.abs(p.x - p.cluster.x) > 100) p.vx *= -1;
        if (Math.abs(p.y - p.cluster.y) > 100) p.vy *= -1;
    });

    // Met à jour la position des particules et des lignes
    svg.selectAll(".particle")
        .data(particles)
        .attr("cx", d => d.x)
        .attr("cy", d => d.y);

    svg.selectAll(".line")
        .data(particles)
        .attr("x1", d => d.x)
        .attr("y1", d => d.y)
        .attr("x2", d => d.cluster.x)
        .attr("y2", d => d.cluster.y);
}

// Ajouter une boucle d'animation
d3.timer(animateParticles);

// Ajoute un événement pour le bouton "Générer"
button.on("click", () => {
    const question = input.property("value");

    if (question.trim() !== "") {
        fetch("/api/analyze", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ question }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.keywords) {
                    generateClustersFromKeywords(data.keywords); // Mots-clés pour D3.js
                }
                if (data.form_html) {
                    d3.select("#controls").html(data.form_html); // Injecter le formulaire
                }
            });
    }
});

// question reformulée
d3.select("#controls").on("click", (event) => {
    if (event.target && event.target.id === "submit-question") {
        const questionElement = d3.select("#reformulated-question");

        // Vérifie si l'élément existe avant d'accéder à sa propriété
        if (!questionElement.empty()) {
            const reformulatedQuestion = questionElement.property("value");

            if (reformulatedQuestion.trim() !== "") {
                fetch("/api/chat", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ question: reformulatedQuestion }),
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.response) {
                            d3.select("#controls").append("div").html(`<p>Réponse : ${data.response}</p>`);
                        }
                    })
                    .catch((error) => {
                        console.error("Erreur lors de l'envoi de la question reformulée :", error);
                    });
            } else {
                console.warn("La question reformulée est vide !");
            }
        } else {
            console.error("L'élément #reformulated-question n'existe pas dans le DOM.");
        }
    }
});