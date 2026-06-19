import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import App from "./App";
import "./index.css";

// StrictMode dev'de component'leri iki kez mount eder; lazy + AnimatePresence
// + router hook bileşimi runtime'da "removeChild" hatasına yol açıyor. Üretim
// modunda StrictMode zaten pasif olduğu için production render güvenli.
ReactDOM.createRoot(document.getElementById("root")!).render(
 <BrowserRouter>
 <App />
 </BrowserRouter>
);
