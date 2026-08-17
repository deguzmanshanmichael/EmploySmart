import api from "./api";

const getMyApplications = () => {
  return api.get("/applications/my");
};

export default {
  getMyApplications,
};