// Configuration initiale
const width = 800;
const height = 600;

const svg = d3.select("svg")
    .attr("width", width)
    .attr("height", height)
    .style("background", "#101020");

// Clusters de mots-clés
const clusters = [
    { keyword: "intelligence", x: 200, y: 300, size: 30, color: "#FF5733" },
    { keyword: "artificielle", x: 600, y: 300, size: 30, color: "#33FF57" },
    { keyword: "apprentissage", x: 300, y: 500, size: 30, color: "#3357FF" },
    { keyword: "automatique", x: 500, y: 500, size: 30, color: "#F3FF33" }
];

// Relations fixes
const relationships = [
    { source: "intelligence", target: "artificielle" },
    { source: "apprentissage", target: "automatique" }
];

// Particules associées aux clusters
const particles = clusters.map(cluster => ({
    cluster,
    x: cluster.x + Math.random() * 100 - 50,
    y: cluster.y + Math.random() * 100 - 50,
    vx: Math.random() * 2 - 1,
    vy: Math.random() * 2 - 1
}));

// Dessiner les clusters
svg.selectAll(".cluster")
    .data(clusters)
    .enter()
    .append("circle")
    .attr("class", "cluster")
    .attr("cx", d => d.x)
    .attr("cy", d => d.y)
    .attr("r", d => d.size)
    .attr("fill", d => d.color);

// Ajouter les relations fixes entre clusters
const keywordMap = new Map(clusters.map(d => [d.keyword, d]));

svg.selectAll(".fixed-link")
    .data(relationships)
    .enter()
    .append("line")
    .attr("class", "fixed-link")
    .attr("stroke", "white")
    .attr("stroke-width", 2)
    .attr("x1", d => keywordMap.get(d.source).x)
    .attr("y1", d => keywordMap.get(d.source).y)
    .attr("x2", d => keywordMap.get(d.target).x)
    .attr("y2", d => keywordMap.get(d.target).y)
    .attr("opacity", 0.5);

// Simulation des forces
const simulation = d3.forceSimulation(particles)
    .force("x", d3.forceX(d => d.cluster.x).strength(0.1))
    .force("y", d3.forceY(d => d.cluster.y).strength(0.1))
    .force("collision", d3.forceCollide(20))
    .alphaDecay(0.02) // Réduction progressive de l'animation
    .on("tick", ticked);

// Arrêter la simulation après 5 secondes
setTimeout(() => {
    simulation.stop(); // Stopper l'animation
    console.log("Simulation arrêtée.");
}, 5000);

// Mise à jour des particules pendant l'animation
function ticked() {
    svg.selectAll(".particle")
        .data(particles)
        .join("circle")
        .attr("class", "particle")
        .attr("r", 5)
        .attr("fill", "lightblue")
        .attr("cx", d => d.x)
        .attr("cy", d => d.y);
}
